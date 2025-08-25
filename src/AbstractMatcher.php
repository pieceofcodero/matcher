<?php

namespace Pieceofcodero\Matcher;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * AbstractMatcher provides a base implementation for concrete matchers.
 * It supports adding rules, loading rulesets from files, and registering custom rule types.
 * Subclasses should implement specific matching logic by defining built-in rule types for most common use cases.
 * See StringMatcher for a concrete example.
 */
abstract class AbstractMatcher
{
    protected array $rules = [];
    protected array $ruleHandlers = [];

    public function __construct()
    {
        $this->defineBuiltinRuleTypes();
    }

    /**
     * Define built-in rule types.
     * Subclasses should override this method to register their built-in rule types.
     */
    protected function defineBuiltinRuleTypes(): void
    {
        // No-op in the abstract class
    }

    /**
     * Add a rule.
     *
     * @param string $type One of the registered rule types
     * @param mixed $pattern The pattern (string, array, or any type appropriate for the matcher)
     */
    public function addRule(string $type, $pattern): void
    {
        if (is_array($pattern)) {
            foreach ($pattern as $p) {
                $this->rules[] = ['type' => $type, 'pattern' => $p];
            }
        } else {
            $this->rules[] = ['type' => $type, 'pattern' => $pattern];
        }
    }

    /**
     * Load a ruleset from a standalone PHP file.
     * The file must return an array of rules: [['type', 'pattern'], ...]
     *
     * @param string $filePath
     */
    public function loadRulesetFromFile(string $filePath): void
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("Ruleset file not found or not readable: $filePath");
        }

        $rules = require $filePath;

        if (!is_array($rules)) {
            throw new UnexpectedValueException("Ruleset file must return an array of rules: $filePath");
        }

        $this->addRuleset($rules);
    }

    /**
     * Add multiple rules at once.
     *
     * @param array $rules Array of rules in the form [['type', 'pattern'], ...]
     */
    public function addRuleset(array $rules): void
    {
        foreach ($rules as $rule) {
            if (!isset($rule[0], $rule[1])) {
                continue; // skip invalid entries
            }
            $this->addRule($rule[0], $rule[1]);
        }
    }

    /**
     * Register a rule type with a callback.
     * The callback receives the value to match and pattern and should return a boolean.
     *
     * @param string $type
     * @param callable $handler function($value, $pattern): bool
     */
    public function registerRuleType(string $type, callable $handler): void
    {
        $this->ruleHandlers[$type] = $handler;
    }

    /**
     * Check if the given value matches any of the rules.
     *
     * @param mixed $value The value to match against the rules
     * @return bool True if a match is found, false otherwise
     */
    public function matches($value): bool
    {
        foreach ($this->rules as $rule) {
            $type = $rule['type'];
            $pattern = $rule['pattern'];

            if (isset($this->ruleHandlers[$type])) {
                $callback = $this->ruleHandlers[$type];
                if ($callback($value, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }
}
