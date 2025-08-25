<?php

namespace Pieceofcodero\Matcher\Tests;

use Pieceofcodero\Matcher\StringMatcher;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use UnexpectedValueException;

class StringMatcherTest extends TestCase
{
    private StringMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new StringMatcher();
    }

    /**
     * Test addRule with both single string pattern and array of patterns
     */
    public function testAddRule(): void
    {
        // Test with a single pattern
        $this->matcher->addRule('startsWith', '/api');
        $this->assertTrue($this->matcher->matches('/api/users'));
        $this->assertTrue($this->matcher->matches('/api'));
        $this->assertFalse($this->matcher->matches('/users/api'));
        $this->assertFalse($this->matcher->matches(''));

        // Reset matcher and test with array of patterns
        $this->matcher = new StringMatcher();
        $this->matcher->addRule('startsWith', ['/api', '/admin']);
        $this->assertTrue($this->matcher->matches('/api/users'));
        $this->assertTrue($this->matcher->matches('/admin/dashboard'));
        $this->assertFalse($this->matcher->matches('/users'));
    }

    /**
     * Test all built-in rule types (startsWith, contains, regex)
     */
    public function testBuiltInRuleTypes(): void
    {
        // Test 'startsWith' rule type
        $this->matcher->addRule('startsWith', '/api');
        $this->assertTrue($this->matcher->matches('/api/users'));
        $this->assertFalse($this->matcher->matches('/users/api'));

        // Reset and test 'contains' rule type
        $this->matcher = new StringMatcher();
        $this->matcher->addRule('contains', 'api');
        $this->assertTrue($this->matcher->matches('/api/users'));
        $this->assertTrue($this->matcher->matches('/users/api'));
        $this->assertTrue($this->matcher->matches('/users/api/settings'));
        $this->assertFalse($this->matcher->matches('/users'));
        $this->assertFalse($this->matcher->matches(''));

        // Reset and test 'regex' rule type
        $this->matcher = new StringMatcher();
        $this->matcher->addRule('regex', '/^\/api\/users\/\d+$/');
        $this->assertTrue($this->matcher->matches('/api/users/123'));
        $this->assertTrue($this->matcher->matches('/api/users/456'));
        $this->assertFalse($this->matcher->matches('/api/users/abc'));
        $this->assertFalse($this->matcher->matches('/api/users'));
        $this->assertFalse($this->matcher->matches('/api'));
    }

    /**
     * Test multiple rules together
     */
    public function testMultipleRules(): void
    {
        $this->matcher->addRule('startsWith', '/api');
        $this->matcher->addRule('contains', 'admin');
        $this->matcher->addRule('regex', '/^\/users\/\d+$/');

        // Should match startsWith rule
        $this->assertTrue($this->matcher->matches('/api/users'));

        // Should match contains rule
        $this->assertTrue($this->matcher->matches('/some/admin/page'));

        // Should match regex rule
        $this->assertTrue($this->matcher->matches('/users/123'));

        // Should not match any rule
        $this->assertFalse($this->matcher->matches('/dashboard'));
    }

    /**
     * Test loading ruleset from a valid file
     */
    public function testLoadRulesetFromValidFile(): void
    {
        // Test with single patterns
        $tempFile = sys_get_temp_dir() . '/valid_ruleset.php';
        file_put_contents($tempFile, '<?php return [["startsWith", "/api"], ["contains", "admin"]]; ?>');

        $this->matcher->loadRulesetFromFile($tempFile);

        $this->assertTrue($this->matcher->matches('/api/users'));
        $this->assertTrue($this->matcher->matches('/some/admin/page'));
        $this->assertFalse($this->matcher->matches('/users'));

        // Clean up
        unlink($tempFile);

        // Test with array patterns
        $this->matcher = new StringMatcher(); // Reset the matcher

        $tempFile = sys_get_temp_dir() . '/array_ruleset.php';
        file_put_contents($tempFile, '<?php return [["startsWith", ["/api", "/admin"]], ["contains", ["user", "profile"]]]; ?>');

        $this->matcher->loadRulesetFromFile($tempFile);

        $this->assertTrue($this->matcher->matches('/api/something'));
        $this->assertTrue($this->matcher->matches('/admin/dashboard'));
        $this->assertTrue($this->matcher->matches('/something/user/view'));
        $this->assertTrue($this->matcher->matches('/edit/profile/123'));
        $this->assertFalse($this->matcher->matches('/dashboard'));

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test loading ruleset from a non-existent file
     */
    public function testLoadRulesetFromNonExistentFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->matcher->loadRulesetFromFile('/non/existent/file.php');
    }

    /**
     * Test loading ruleset from a file that doesn't return an array
     */
    public function testLoadRulesetFromInvalidFile(): void
    {
        // Create a temporary file that doesn't return an array
        $tempFile = sys_get_temp_dir() . '/invalid_ruleset.php';
        file_put_contents($tempFile, '<?php return "not an array"; ?>');

        $this->expectException(UnexpectedValueException::class);

        try {
            $this->matcher->loadRulesetFromFile($tempFile);
        } finally {
            // Clean up
            unlink($tempFile);
        }
    }

    /**
     * Test loading ruleset from a file with invalid rules
     */
    public function testLoadRulesetWithInvalidRules(): void
    {
        // Create a temporary file with some invalid rules (missing type or pattern)
        $tempFile = sys_get_temp_dir() . '/partially_invalid_ruleset.php';
        file_put_contents($tempFile, '<?php return [["startsWith", "/api"], ["contains"], [null, "/admin"], []]; ?>');

        // This should not throw an exception, but should skip invalid entries
        $this->matcher->loadRulesetFromFile($tempFile);

        // Only the valid rule should be added
        $this->assertTrue($this->matcher->matches('/api/users'));
        $this->assertFalse($this->matcher->matches('/some/admin/page'));

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test basic rule type registration and matching
     */
    public function testBasicRuleType(): void
    {
        // Register a rule type that checks if string ends with the pattern
        $this->matcher->registerRuleType('endsWith', function (string $string, string $pattern): bool {
            return substr($string, -strlen($pattern)) === $pattern;
        });

        // Test with a single pattern
        $this->matcher->addRule('endsWith', '.jpg');
        $this->assertTrue($this->matcher->matches('/images/photo.jpg'));
        $this->assertTrue($this->matcher->matches('/profile.jpg'));
        $this->assertFalse($this->matcher->matches('/jpg/profile'));
        $this->assertFalse($this->matcher->matches('/images/photo.png'));

        // Test with array patterns
        $this->matcher = new StringMatcher(); // Reset
        $this->matcher->registerRuleType('endsWith', function (string $string, string $pattern): bool {
            return substr($string, -strlen($pattern)) === $pattern;
        });
        $this->matcher->addRule('endsWith', ['.jpg', '.png', '.gif']);
        $this->assertTrue($this->matcher->matches('/image.jpg'));
        $this->assertTrue($this->matcher->matches('/photo.png'));
        $this->assertTrue($this->matcher->matches('/avatar.gif'));
        $this->assertFalse($this->matcher->matches('/document.pdf'));
    }

    /**
     * Test complex rule type with logic
     */
    public function testComplexRuleType(): void
    {
        // Register a rule type that checks if string contains a numeric ID matching a specific format
        $this->matcher->registerRuleType('containsNumericId', function (string $string, string $pattern): bool {
            // Pattern is expected to be a regex pattern for the ID format
            preg_match_all($pattern, $string, $matches);
            return !empty($matches[0]);
        });

        $this->matcher->addRule('containsNumericId', '/id-(\d+)/');

        $this->assertTrue($this->matcher->matches('/users/id-123/profile'));
        $this->assertTrue($this->matcher->matches('/products/id-456'));
        $this->assertFalse($this->matcher->matches('/users/profile/123'));
        $this->assertFalse($this->matcher->matches('/users/id-abc'));
    }

    /**
     * Test multiple rule types
     */
    public function testMultipleRuleTypes(): void
    {
        // Register 'endsWith' rule
        $this->matcher->registerRuleType('endsWith', function (string $string, string $pattern): bool {
            return substr($string, -strlen($pattern)) === $pattern;
        });

        // Register 'hasQueryParam' rule
        $this->matcher->registerRuleType('hasQueryParam', function (string $string, string $pattern): bool {
            $parts = explode('?', $string, 2);
            if (count($parts) !== 2) {
                return false;
            }
            parse_str($parts[1], $params);
            return isset($params[$pattern]);
        });

        $this->matcher->addRule('endsWith', '.php');
        $this->matcher->addRule('hasQueryParam', 'debug');

        $this->assertTrue($this->matcher->matches('/index.php'));
        $this->assertTrue($this->matcher->matches('/api/users?debug=true'));
        $this->assertTrue($this->matcher->matches('/api/settings.php?user=1&debug=0'));
        $this->assertFalse($this->matcher->matches('/index.html'));
        $this->assertFalse($this->matcher->matches('/api/users?user=1'));
    }

    /**
     * Test loading ruleset from a file with user-defined rule types
     */
    public function testRuleTypeFromRulesetFile(): void
    {
        // Register rule type first
        $this->matcher->registerRuleType('endsWith', function (string $string, string $pattern): bool {
            return substr($string, -strlen($pattern)) === $pattern;
        });

        // Create a temporary file with rule type
        $tempFile = sys_get_temp_dir() . '/custom_ruleset.php';
        file_put_contents($tempFile, '<?php return [["endsWith", ".html"], ["startsWith", "/pages/"]]; ?>');

        $this->matcher->loadRulesetFromFile($tempFile);

        $this->assertTrue($this->matcher->matches('/index.html'));
        $this->assertTrue($this->matcher->matches('/pages/about'));
        $this->assertFalse($this->matcher->matches('/index.php'));
        $this->assertFalse($this->matcher->matches('/api/pages'));

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test that built-in rule types can be successfully overridden
     */
    public function testCanOverrideBuiltInRuleTypes(): void
    {
        // Create a handler for the 'contains' built-in rule type that always returns true
        $this->matcher->registerRuleType('contains', function (): bool {
            return true;
        });

        // Add a rule using this overridden built-in type
        $this->matcher->addRule('contains', 'pattern-doesnt-matter');

        // Should always match due to our override, even with inputs that wouldn't match the default implementation
        $this->assertTrue($this->matcher->matches('any-string-should-match'));
    }

    /**
     * Test adding multiple rules at once using addRuleset
     */
    public function testAddRuleset(): void
    {
        // Test adding a complete ruleset at once
        $ruleset = [
            ['startsWith', '/api'],
            ['contains', 'admin'],
            ['regex', '/^\/users\/\d+$/'],
            ['startsWith', ['/admin', '/dashboard']] // Testing array patterns in ruleset
        ];

        $this->matcher->addRuleset($ruleset);

        // Verify one match for each rule type to ensure all were added correctly
        $this->assertTrue($this->matcher->matches('/api/users'));         // Match startsWith /api
        $this->assertTrue($this->matcher->matches('/some/admin/page'));   // Match contains admin
        $this->assertTrue($this->matcher->matches('/users/123'));         // Match regex
        $this->assertTrue($this->matcher->matches('/admin/settings'));    // Match startsWith /admin from array

        // Verify non-match
        $this->assertFalse($this->matcher->matches('/app'));             // Shouldn't match any rule
    }

    /**
     * Test adding a ruleset with invalid entries
     */
    public function testAddRulesetWithInvalidEntries(): void
    {
        $ruleset = [
            ['startsWith', '/api'],      // Valid
            ['contains'],                // Invalid (missing pattern)
            [null, '/admin'],            // Invalid (missing type)
            []                           // Invalid (empty rule)
        ];

        $this->matcher->addRuleset($ruleset);

        // Only the valid rule should be added
        $this->assertTrue($this->matcher->matches('/api/users'));
        $this->assertFalse($this->matcher->matches('/some/admin/page'));
    }

    /**
     * Test that rule types can be successfully overridden
     */
    public function testCanOverrideRuleTypes(): void
    {
        // First, register a rule type
        $this->matcher->registerRuleType('customType', function (string $string, string $pattern): bool {
            return $string === $pattern; // Exact match
        });

        // Add a rule with the original implementation
        $this->matcher->addRule('customType', 'exact-match');

        // Verify original implementation works
        $this->assertTrue($this->matcher->matches('exact-match'));
        $this->assertFalse($this->matcher->matches('not-a-match'));

        // Now override the rule type
        $this->matcher->registerRuleType('customType', function (string $string, string $pattern): bool {
            return strpos($string, $pattern) !== false; // Contains instead of exact match
        });

        // Add a rule with the new implementation
        $this->matcher->addRule('customType', 'partial');

        // Verify the override works - should now match anything containing 'partial'
        $this->assertTrue($this->matcher->matches('this-is-a-partial-match'));
        $this->assertTrue($this->matcher->matches('partial-match'));
        $this->assertFalse($this->matcher->matches('not-a-match'));
    }
}
