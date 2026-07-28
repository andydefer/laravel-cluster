<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\TokenType;
use AndyDefer\LaravelCluster\Records\TokenRecord;

final class TokenRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TokenRecord::class);
    }

    public function operators(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type === TokenType::OPERATOR
        );
    }

    public function identifiers(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type === TokenType::IDENTIFIER
        );
    }

    public function parens(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type === TokenType::PAREN
        );
    }

    public function ofType(TokenType $type): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type === $type
        );
    }

    public function withValue(string $value): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->value === $value
        );
    }

    public function withValues(array $values): self
    {
        return $this->filter(
            fn (TokenRecord $token) => in_array($token->value, $values, true)
        );
    }

    public function withoutEnd(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type !== TokenType::END
        );
    }

    public function comparisonOperators(): self
    {
        return $this->filter(
            fn (TokenRecord $token) => $token->type === TokenType::OPERATOR &&
                in_array($token->value, ComparisonOperator::values(), true)
        );
    }

    public function logicalOperators(): self
    {
        $operators = ['AND', 'OR', 'NOT'];

        return $this->filter(
            fn (TokenRecord $token) => $token->type === TokenType::OPERATOR &&
                in_array($token->value, $operators, true)
        );
    }

    public function atPosition(int $position): ?TokenRecord
    {
        foreach ($this->items as $token) {
            if ($token->position === $position) {
                return $token;
            }
        }

        return null;
    }

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

    public function values(): StringTypedCollection
    {
        $values = new StringTypedCollection;

        foreach ($this->items as $token) {
            $values->add($token->value);
        }

        return $values;
    }
}
