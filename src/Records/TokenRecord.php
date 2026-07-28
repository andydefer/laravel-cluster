<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelCluster\Enums\TokenType;

/**
 * Represents a single token produced by the lexer during tokenization.
 *
 * A TokenRecord captures the essential information about a token in the query:
 * its type (identifier, operator, parenthesis, or end marker), its value (the
 * actual text), and its position in the original input string.
 *
 * This immutable record is used throughout the parsing pipeline to build
 * the Abstract Syntax Tree (AST) of the query.
 *
 * @example
 * $token = new TokenRecord(
 *     TokenType::IDENTIFIER,
 *     'username',
 *     0
 * );
 *
 * echo $token->value; // 'username'
 * echo $token->type->isIdentifier(); // true
 * echo $token->position; // 0
 */
final class TokenRecord extends AbstractRecord
{
    /**
     * Initializes a new token record with the specified attributes.
     *
     * @param  TokenType  $type  The semantic type of the token
     * @param  string  $value  The actual token value (e.g., 'username', '=', '(')
     * @param  int  $position  The zero-based position in the original input string
     */
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly int $position
    ) {}
}
