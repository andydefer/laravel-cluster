<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

/**
 * Enumeration of operator symbols used in the lexer.
 *
 * This enum maps symbolic operators from the input string to their
 * corresponding ComparisonOperator or LogicalOperator enums.
 *
 * @example
 * $token = OperatorToken::fromSymbol('>=');
 * $comparison = $token->getComparisonOperator(); // ComparisonOperator::GREATER_THAN_OR_EQUAL
 */
enum OperatorToken: string
{
    // Comparison operators
    case EQUAL_STRICT = '===';
    case NOT_EQUAL_STRICT = '!==';
    case NOT_EQUAL = '!=';
    case LESS_THAN_OR_EQUAL = '<=';
    case GREATER_THAN_OR_EQUAL = '>=';
    case SPACESHIP = '<=>';
    case EQUAL_LOOSE = '==';
    case EQUAL = '=';
    case LESS_THAN = '<';
    case GREATER_THAN = '>';

    // Logical operators
    case AND = '&';
    case OR = '|';
    case NOT = '!';

    /**
     * Returns the actual operator value as a string.
     *
     * @return string The operator value string
     */
    public function getValue(): string
    {
        return match ($this) {
            self::EQUAL_STRICT => ComparisonOperator::EQUAL_STRICT->value,
            self::NOT_EQUAL_STRICT => ComparisonOperator::NOT_EQUAL_STRICT->value,
            self::NOT_EQUAL => ComparisonOperator::NOT_EQUAL->value,
            self::LESS_THAN_OR_EQUAL => ComparisonOperator::LESS_THAN_OR_EQUAL->value,
            self::GREATER_THAN_OR_EQUAL => ComparisonOperator::GREATER_THAN_OR_EQUAL->value,
            self::SPACESHIP => ComparisonOperator::SPACESHIP->value,
            self::EQUAL_LOOSE => ComparisonOperator::EQUAL_LOOSE->value,
            self::EQUAL => ComparisonOperator::EQUAL->value,
            self::LESS_THAN => ComparisonOperator::LESS_THAN->value,
            self::GREATER_THAN => ComparisonOperator::GREATER_THAN->value,
            self::AND => LogicalOperator::AND->value,
            self::OR => LogicalOperator::OR->value,
            self::NOT => 'NOT',
        };
    }

    /**
     * Converts the operator token to a ComparisonOperator enum.
     *
     * @return ?ComparisonOperator The comparison operator or null
     */
    public function getComparisonOperator(): ?ComparisonOperator
    {
        return match ($this) {
            self::EQUAL_STRICT => ComparisonOperator::EQUAL_STRICT,
            self::NOT_EQUAL_STRICT => ComparisonOperator::NOT_EQUAL_STRICT,
            self::NOT_EQUAL => ComparisonOperator::NOT_EQUAL,
            self::LESS_THAN_OR_EQUAL => ComparisonOperator::LESS_THAN_OR_EQUAL,
            self::GREATER_THAN_OR_EQUAL => ComparisonOperator::GREATER_THAN_OR_EQUAL,
            self::SPACESHIP => ComparisonOperator::SPACESHIP,
            self::EQUAL_LOOSE => ComparisonOperator::EQUAL_LOOSE,
            self::EQUAL => ComparisonOperator::EQUAL,
            self::LESS_THAN => ComparisonOperator::LESS_THAN,
            self::GREATER_THAN => ComparisonOperator::GREATER_THAN,
            default => null,
        };
    }

    /**
     * Converts the operator token to a LogicalOperator enum.
     *
     * @return ?LogicalOperator The logical operator or null
     */
    public function getLogicalOperator(): ?LogicalOperator
    {
        return match ($this) {
            self::AND => LogicalOperator::AND,
            self::OR => LogicalOperator::OR,
            self::NOT => LogicalOperator::NOT,
            default => null,
        };
    }

    /**
     * Determines if the token is a comparison operator.
     *
     * @return bool True if the token is a comparison operator
     */
    public function isComparison(): bool
    {
        return $this->getComparisonOperator() !== null;
    }

    /**
     * Determines if the token is a logical operator.
     *
     * @return bool True if the token is a logical operator
     */
    public function isLogical(): bool
    {
        return $this->getLogicalOperator() !== null;
    }

    /**
     * Determines if the token is the NOT operator.
     *
     * @return bool True if the token is NOT
     */
    public function isNot(): bool
    {
        return $this === self::NOT;
    }

    /**
     * Creates an enum instance from a symbol string.
     *
     * @param  string  $symbol  The operator symbol
     * @return ?self The enum instance or null if not found
     */
    public static function fromSymbol(string $symbol): ?self
    {
        return match ($symbol) {
            '===' => self::EQUAL_STRICT,
            '!==' => self::NOT_EQUAL_STRICT,
            '!=' => self::NOT_EQUAL,
            '<=' => self::LESS_THAN_OR_EQUAL,
            '>=' => self::GREATER_THAN_OR_EQUAL,
            '<=>' => self::SPACESHIP,
            '==' => self::EQUAL_LOOSE,
            '=' => self::EQUAL,
            '<' => self::LESS_THAN,
            '>' => self::GREATER_THAN,
            '&' => self::AND,
            '|' => self::OR,
            '!' => self::NOT,
            default => null,
        };
    }

    /**
     * Returns a mapping of operator symbols to their values.
     *
     * @return array<string, string> An array mapping symbols to values
     */
    public static function mapping(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->getValue();
        }

        return $map;
    }

    /**
     * Returns all operator symbols as an array.
     *
     * @return array<string> Array of all operator symbols
     */
    public static function symbols(): array
    {
        return array_keys(self::mapping());
    }
}
