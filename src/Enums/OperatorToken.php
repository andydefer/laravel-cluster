<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

enum OperatorToken: string
{
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

    case AND = '&';
    case OR = '|';
    case NOT = '!';

    case EXISTS = '*';
    case NOT_EXISTS = '#';

    case LIKE = '=~';
    case NOT_LIKE = '!~';

    case SUB_OPEN = '[';
    case SUB_CLOSE = ']';

    case COMMA = ',';

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
            self::EXISTS => ComparisonOperator::EXISTS->value,
            self::NOT_EXISTS => ComparisonOperator::NOT_EXISTS->value,
            self::LIKE => ComparisonOperator::LIKE->value,
            self::NOT_LIKE => ComparisonOperator::NOT_LIKE->value,
            self::SUB_OPEN => '[',
            self::SUB_CLOSE => ']',
            self::COMMA => ',',
        };
    }

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
            self::EXISTS => ComparisonOperator::EXISTS,
            self::NOT_EXISTS => ComparisonOperator::NOT_EXISTS,
            self::LIKE => ComparisonOperator::LIKE,
            self::NOT_LIKE => ComparisonOperator::NOT_LIKE,
            default => null,
        };
    }

    public function getLogicalOperator(): ?LogicalOperator
    {
        return match ($this) {
            self::AND => LogicalOperator::AND,
            self::OR => LogicalOperator::OR,
            default => null,
        };
    }

    public function isComparison(): bool
    {
        return $this->getComparisonOperator() !== null && in_array($this->getComparisonOperator(), [
            ComparisonOperator::EQUAL,
            ComparisonOperator::EQUAL_LOOSE,
            ComparisonOperator::EQUAL_STRICT,
            ComparisonOperator::NOT_EQUAL,
            ComparisonOperator::NOT_EQUAL_STRICT,
            ComparisonOperator::LESS_THAN,
            ComparisonOperator::LESS_THAN_OR_EQUAL,
            ComparisonOperator::GREATER_THAN,
            ComparisonOperator::GREATER_THAN_OR_EQUAL,
            ComparisonOperator::SPACESHIP,
        ], true);
    }

    public function isExistence(): bool
    {
        return $this === self::EXISTS || $this === self::NOT_EXISTS;
    }

    public function isLike(): bool
    {
        return $this === self::LIKE || $this === self::NOT_LIKE;
    }

    public function isLogical(): bool
    {
        return $this->getLogicalOperator() !== null;
    }

    public function isNot(): bool
    {
        return $this === self::NOT;
    }

    public function isBracket(): bool
    {
        return $this === self::SUB_OPEN || $this === self::SUB_CLOSE;
    }

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
            '*' => self::EXISTS,
            '#' => self::NOT_EXISTS,
            '=~' => self::LIKE,
            '!~' => self::NOT_LIKE,
            '[' => self::SUB_OPEN,
            ']' => self::SUB_CLOSE,
            ',' => self::COMMA,
            default => null,
        };
    }

    public static function mapping(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->getValue();
        }

        return $map;
    }

    public static function symbols(): array
    {
        return array_keys(self::mapping());
    }
}
