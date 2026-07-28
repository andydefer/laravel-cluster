<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\TokenType;
use AndyDefer\LaravelCluster\Records\TokenRecord;

/**
 * A specialized collection for managing TokenRecord objects with filtering capabilities.
 *
 * This collection provides a fluent interface for querying and filtering tokens
 * based on their type, value, position, and semantic categories (operators, identifiers, etc.).
 *
 * @method TokenRecord|null first()
 * @method TokenRecord|null last()
 *
 * @example
 * $tokens = new TokenRecordCollection();
 * $tokens->add($token1)->add($token2);
 *
 * $operators = $tokens->operators();
 * $comparisonOps = $tokens->comparisonOperators();
 * $firstToken = $tokens->atPosition(0);
 */
final class TokenRecordCollection extends AbstractTypedCollection
{
    /**
     * Initializes the collection with TokenRecord type validation.
     */
    public function __construct()
    {
        parent::__construct(TokenRecord::class);
    }

    /**
     * Filters the collection to include only operator tokens.
     *
     * @return self A new collection containing only operator tokens
     */
    public function operators(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type->isOperator()
        );
    }

    /**
     * Filters the collection to include only identifier tokens.
     *
     * @return self A new collection containing only identifier tokens
     */
    public function identifiers(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type->isIdentifier()
        );
    }

    /**
     * Filters the collection to include only parenthesis tokens.
     *
     * @return self A new collection containing only parenthesis tokens
     */
    public function parens(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type->isParen()
        );
    }

    /**
     * Filters the collection to include tokens of a specific type.
     *
     * @param  TokenType  $type  The token type to filter by
     * @return self A new collection containing only tokens of the specified type
     */
    public function ofType(TokenType $type): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type === $type
        );
    }

    /**
     * Filters the collection to include tokens with a specific value.
     *
     * @param  string  $value  The value to match against
     * @return self A new collection containing only tokens with the specified value
     */
    public function withValue(string $value): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->value === $value
        );
    }

    /**
     * Filters the collection to include tokens whose values are in the given array.
     *
     * @param  array<string>  $values  The array of acceptable values
     * @return self A new collection containing only tokens with matching values
     */
    public function withValues(array $values): self
    {
        return $this->filter(
            fn (TokenRecord $token) => in_array($token->value, $values, true)
        );
    }

    /**
     * Filters the collection to exclude end-of-expression tokens.
     *
     * @return self A new collection without end tokens
     */
    public function withoutEnd(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => ! $token->type->isEnd()
        );
    }

    /**
     * Filters the collection to include only comparison operator tokens.
     *
     * Includes operators like =, !=, <, >, <=, >=, LIKE, etc.
     *
     * @return self A new collection containing only comparison operator tokens
     */
    public function comparisonOperators(): self
    {
        $comparisonValues = ComparisonOperator::values();

        return $this->filter(
            fn (TokenRecord $token) => $token->type->isOperator() &&
                in_array($token->value, $comparisonValues, true)
        );
    }

    /**
     * Filters the collection to include only logical operator tokens.
     *
     * Includes operators like AND, OR, NOT.
     *
     * @return self A new collection containing only logical operator tokens
     */
    public function logicalOperators(): self
    {
        $logicalValues = LogicalOperator::values();

        return $this->filter(
            fn (TokenRecord $token) => $token->type->isOperator() &&
                in_array($token->value, $logicalValues, true)
        );
    }

    /**
     * Retrieves the token at a specific position in the expression.
     *
     * @param  int  $position  The position index to look up
     * @return TokenRecord|null The token at the position, or null if not found
     */
    public function atPosition(int $position): ?TokenRecord
    {
        foreach ($this->items as $token) {
            if ($token->position === $position) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Creates a new collection containing tokens from the specified position onward.
     *
     * @param  int  $position  The starting position (inclusive)
     * @return self A new collection with tokens from the position onward
     */
    public function fromPosition(int $position): self
    {
        $collection = new self;

        foreach ($this->items as $token) {
            if ($token->position >= $position) {
                $collection->add($token);
            }
        }

        return $collection;
    }

    /**
     * Extracts and returns the values of all tokens as a string collection.
     *
     * @return StringTypedCollection A collection containing only the token values
     */
    public function values(): StringTypedCollection
    {
        $values = new StringTypedCollection;

        foreach ($this->items as $token) {
            $values->add($token->value);
        }

        return $values;
    }

    /**
     * Filters the collection to include only pure comparison operators.
     *
     * Returns only comparison operators (e.g., =, !=, <, >, <=, >=, LIKE)
     * while explicitly excluding logical operators (AND, OR, NOT).
     *
     * @return self A new collection containing only pure comparison operator tokens
     */
    public function pureComparisonOperators(): self
    {
        $comparisonValues = ComparisonOperator::values();
        $logicalValues = LogicalOperator::values();

        return $this->filter(
            fn (TokenRecord $token) => $token->type->isOperator() &&
                in_array($token->value, $comparisonValues, true) &&
                ! in_array($token->value, $logicalValues, true)
        );
    }

    /**
     * Filters the collection to include only pure logical operators.
     *
     * Returns only logical operators (AND, OR, NOT) that are not also
     * considered comparison operators.
     *
     * @return self A new collection containing only pure logical operator tokens
     */
    public function pureLogicalOperators(): self
    {
        $logicalValues = LogicalOperator::values();

        return $this->filter(
            fn (TokenRecord $token) => $token->type->isOperator() &&
                in_array($token->value, $logicalValues, true)
        );
    }
}
