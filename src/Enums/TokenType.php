<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

/**
 * Enumeration of token types used in the lexer.
 *
 * This enum classifies tokens by their semantic role in the query:
 * identifiers (values), operators (comparison/logical), parentheses, and end markers.
 *
 * @example
 * $type = TokenType::IDENTIFIER;
 * if ($type->isIdentifier()) {
 *     // Handle identifier token
 * }
 */
enum TokenType
{
    case IDENTIFIER;
    case OPERATOR;
    case PAREN;
    case END;

    /**
     * Determines if the token type is IDENTIFIER.
     *
     * @return bool True if the type is IDENTIFIER
     */
    public function isIdentifier(): bool
    {
        return $this === self::IDENTIFIER;
    }

    /**
     * Determines if the token type is OPERATOR.
     *
     * @return bool True if the type is OPERATOR
     */
    public function isOperator(): bool
    {
        return $this === self::OPERATOR;
    }

    /**
     * Determines if the token type is PAREN (parenthesis).
     *
     * @return bool True if the type is PAREN
     */
    public function isParen(): bool
    {
        return $this === self::PAREN;
    }

    /**
     * Determines if the token type is END (end of expression).
     *
     * @return bool True if the type is END
     */
    public function isEnd(): bool
    {
        return $this === self::END;
    }

    /**
     * Determines if the token type represents a value token.
     *
     * Value tokens include IDENTIFIER and END.
     *
     * @return bool True if the type is a value token
     */
    public function isValue(): bool
    {
        return $this === self::IDENTIFIER || $this === self::END;
    }

    /**
     * Determines if the token type represents a symbol token.
     *
     * Symbol tokens include OPERATOR and PAREN.
     *
     * @return bool True if the type is a symbol token
     */
    public function isSymbol(): bool
    {
        return $this === self::OPERATOR || $this === self::PAREN;
    }

    /**
     * Returns the string representation of the token type.
     */
    public function toString(): string
    {
        return match ($this) {
            self::IDENTIFIER => 'identifier',
            self::OPERATOR => 'operator',
            self::PAREN => 'parenthesis',
            self::END => 'end',
        };
    }
}
