<?php

namespace Pieceofcodero\Matcher;

/**
 * StringMatcher allows filtering strings based on user-defined rules
 * such as startsWith, contains, and regex patterns.
 */
class StringMatcher extends AbstractMatcher
{
    /**
     * Registers built-in string-specific rule handlers.
     */
    protected function defineBuiltinRuleTypes(): void
    {
        $this->registerRuleType('startsWith', function (string $string, string $pattern): bool {
            return strpos($string, $pattern) === 0;
        });

        $this->registerRuleType('contains', function (string $string, string $pattern): bool {
            return strpos($string, $pattern) !== false;
        });

        $this->registerRuleType('regex', function (string $string, string $pattern): bool {
            return (bool)preg_match($pattern, $string);
        });
    }

    /**
     * @param mixed $value Should be a string for this matcher
     * @return bool True if a match is found, false otherwise
     */
    public function matches($value): bool
    {
        // String type validation at runtime
        if (!is_string($value)) {
            return false;
        }

        // Use parent implementation for the actual matching logic
        return parent::matches($value);
    }
}
