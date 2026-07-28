<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

enum TokenType
{
    case IDENTIFIER;
    case OPERATOR;
    case PAREN;
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
}
