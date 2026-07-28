<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Contracts;

use AndyDefer\LaravelCluster\Nodes\Node;

/**
 * Defines the contract for parsing tokenized queries into an AST.
 *
 * The parser takes a collection of tokens and builds a tree of Node objects
 * representing the logical structure of the query.
 *
 * @example
 * $parser = new MyParser();
 * $tokens = $lexer->tokenize('name = "John" AND age > 25');
 * $ast = $parser->parse($tokens);
 * // Returns a root Node representing the entire query
 */
interface ParserInterface
{
    /**
     * Parses a token collection into an Abstract Syntax Tree (AST).
     *
     * @param  string  $query  The query string to parse
     * @return Node The root node of the parsed AST
     */
    public function parse(string $query): Node;
}
