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
 * Typed collection for TokenRecord objects with filtering capabilities.
 *
 * This collection provides specialized methods for filtering tokens by type,
 * value, or position, making it easier to work with token streams from the lexer.
 *
 * @extends AbstractTypedCollection<TokenRecord>
 */
final class TokenRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TokenRecord::class);
    }

    /**
     * Filters and returns only operator tokens.
     *
     * @return self Collection containing only operator tokens
     */
    public function operators(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type->isOperator()
        );
    }

    /**
     * Filters and returns only identifier tokens.
     *
     * @return self Collection containing only identifier tokens
     */
    public function identifiers(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type->isIdentifier()
        );
    }

    /**
     * Filters and returns only parenthesis tokens.
     *
     * @return self Collection containing only parenthesis tokens
     */
    public function parens(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type->isParen()
        );
    }

    /**
     * Filters tokens by their type.
     *
     * @param  TokenType  $type  The token type to filter by
     * @return self Collection containing only tokens of the given type
     */
    public function ofType(TokenType $type): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type === $type
        );
    }

    /**
     * Filters tokens by their value.
     *
     * @param  string  $value  The value to match
     * @return self Collection containing only tokens with the given value
     */
    public function withValue(string $value): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->value === $value
        );
    }

    /**
     * Filters tokens by multiple values.
     *
     * @param  array<string>  $values  The values to match
     * @return self Collection containing only tokens with values in the given array
     */
    public function withValues(array $values): self
    {
        return $this->filter(
            fn (TokenRecord $token) => in_array($token->value, $values, true)
        );
    }

    /**
     * Excludes the END token from the collection.
     *
     * @return self Collection without the END token
     */
    public function withoutEnd(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => ! $token->type->isEnd()
        );
    }

    /**
     * Filters and returns only comparison operator tokens.
     *
     * @return self Collection containing only comparison operators
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
     * Filters and returns only logical operator tokens.
     *
     * @return self Collection containing only logical operators
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
     * Retrieves a token by its position.
     *
     * @param  int  $position  The position to search for
     * @return TokenRecord|null The token at the given position, or null if not found
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
     * Returns all tokens from the given position onwards.
     *
     * @param  int  $position  The starting position
     * @return self Collection containing tokens from the given position
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
     * Returns all token values as a string collection.
     *
     * @return StringTypedCollection The token values
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
     * Filters and returns only pure comparison operator tokens.
     *
     * Excludes logical operators (AND, OR) from the result.
     *
     * @return self Collection containing only pure comparison operators
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
     * Filters and returns only pure logical operator tokens.
     *
     * @return self Collection containing only logical operators
     */
    public function pureLogicalOperators(): self
    {
        $logicalValues = LogicalOperator::values();

        return $this->filter(
            fn (TokenRecord $token) => $token->type->isOperator() &&
                in_array($token->value, $logicalValues, true)
        );
    }

    /**
     * Filters and returns only sub-condition opening bracket tokens ('[').
     *
     * @return self Collection containing only SUB_OPEN tokens
     */
    public function subOpens(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type->isSubOpen()
        );
    }

    /**
     * Filters and returns only sub-condition closing bracket tokens (']').
     *
     * @return self Collection containing only SUB_CLOSE tokens
     */
    public function subCloses(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type->isSubClose()
        );
    }
}
