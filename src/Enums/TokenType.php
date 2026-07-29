<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

enum TokenType
{
    case IDENTIFIER;
    case OPERATOR;
    case PAREN;
    case SUB_OPEN;
    case SUB_CLOSE;
    case END;

    public function isIdentifier(): bool
    {
        return $this === self::IDENTIFIER;
    }

    public function isOperator(): bool
    {
        return $this === self::OPERATOR;
    }

    public function isParen(): bool
    {
        return $this === self::PAREN;
    }

    public function isEnd(): bool
    {
        return $this === self::END;
    }

    public function isValue(): bool
    {
        return $this === self::IDENTIFIER || $this === self::END;
    }

    public function isSymbol(): bool
    {
        return $this === self::OPERATOR || $this === self::PAREN;
    }

    public function isSubOpen(): bool
    {
        return $this === self::SUB_OPEN;
    }

    public function isSubClose(): bool
    {
        return $this === self::SUB_CLOSE;
    }

    public function isBracket(): bool
    {
        return $this === self::SUB_OPEN || $this === self::SUB_CLOSE;
    }

    public function toString(): string
    {
        return match ($this) {
            self::IDENTIFIER => 'identifier',
            self::OPERATOR => 'operator',
            self::PAREN => 'parenthesis',
            self::SUB_OPEN => 'sub_open',
            self::SUB_CLOSE => 'sub_close',
            self::END => 'end',
        };
    }
}
