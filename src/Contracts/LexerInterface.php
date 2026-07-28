<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Contracts;

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;

/**
 * Defines the contract for tokenizing input strings into a collection of tokens.
 *
 * The lexer is responsible for converting a raw query string into a structured
 * collection of tokens that can be later parsed into an Abstract Syntax Tree (AST).
 *
 * @example
 * $lexer = new MyLexer();
 * $tokens = $lexer->tokenize('name = "John" AND age > 25');
 * // Returns a TokenRecordCollection with IDENTIFIER, OPERATOR, and VALUE tokens
 */
interface LexerInterface
{
    /**
     * Tokenizes the input string into a collection of token records.
     *
     * @param  string  $input  The raw input string to tokenize
     * @return TokenRecordCollection A collection of tokens representing the input
     */
    public function tokenize(string $input): TokenRecordCollection;
}
