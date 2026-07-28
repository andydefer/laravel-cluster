<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Collections;

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;
use AndyDefer\LaravelCluster\Enums\TokenType;
use AndyDefer\LaravelCluster\Records\TokenRecord;
use PHPUnit\Framework\TestCase;

final class TokenRecordCollectionTest extends TestCase
{
    private TokenRecordCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = new TokenRecordCollection;

        $this->collection->add(
            new TokenRecord(TokenType::IDENTIFIER, 'status', 0),
            new TokenRecord(TokenType::OPERATOR, '=', 7),
            new TokenRecord(TokenType::IDENTIFIER, 'active', 9),
            new TokenRecord(TokenType::OPERATOR, 'AND', 16),
            new TokenRecord(TokenType::PAREN, '(', 18),
            new TokenRecord(TokenType::IDENTIFIER, 'role', 19),
            new TokenRecord(TokenType::OPERATOR, '=', 24),
            new TokenRecord(TokenType::IDENTIFIER, 'admin', 26),
            new TokenRecord(TokenType::OPERATOR, 'OR', 32),
            new TokenRecord(TokenType::IDENTIFIER, 'role', 35),
            new TokenRecord(TokenType::OPERATOR, '=', 40),
            new TokenRecord(TokenType::IDENTIFIER, 'doctor', 42),
            new TokenRecord(TokenType::PAREN, ')', 48),
            new TokenRecord(TokenType::END, '', 49),
        );
    }

    // ==================== FILTER TESTS ====================

    public function test_operators(): void
    {
        $result = $this->collection->operators();

        $this->assertCount(5, $result);
        $this->assertEquals('=', $result->first()?->value);
    }

    public function test_identifiers(): void
    {
        $result = $this->collection->identifiers();

        $this->assertCount(6, $result);
        $this->assertEquals('status', $result->first()?->value);
    }

    public function test_parens(): void
    {
        $result = $this->collection->parens();

        $this->assertCount(2, $result);
        $this->assertEquals('(', $result->first()?->value);
        $this->assertEquals(')', $result->last()?->value);
    }

    public function test_of_type(): void
    {
        $result = $this->collection->ofType(TokenType::IDENTIFIER);

        $this->assertCount(6, $result);
        $this->assertEquals('status', $result->first()?->value);
    }

    public function test_with_value(): void
    {
        $result = $this->collection->withValue('role');

        $this->assertCount(2, $result);
        $this->assertEquals('role', $result->first()?->value);
    }

    public function test_with_values(): void
    {
        $result = $this->collection->withValues(['role', 'status']);

        $this->assertCount(3, $result);
        $this->assertEquals('status', $result->first()?->value);
    }

    public function test_without_end(): void
    {
        $result = $this->collection->withoutEnd();

        $this->assertCount(13, $result);
        $this->assertNotEquals(TokenType::END, $result->last()?->type);
    }

    public function test_comparison_operators(): void
    {
        $result = $this->collection->comparisonOperators();

        $this->assertCount(3, $result);
        $this->assertEquals('=', $result->first()?->value);
    }

    public function test_logical_operators(): void
    {
        $result = $this->collection->logicalOperators();

        $this->assertCount(2, $result);
        $this->assertEquals('AND', $result->first()?->value);
        $this->assertEquals('OR', $result->last()?->value);
    }

    // ==================== POSITION TESTS ====================

    public function test_at_position(): void
    {
        $token = $this->collection->atPosition(7);

        $this->assertNotNull($token);
        $this->assertEquals('=', $token->value);
        $this->assertEquals(TokenType::OPERATOR, $token->type);
    }

    public function test_at_position_not_found(): void
    {
        $token = $this->collection->atPosition(100);

        $this->assertNull($token);
    }

    public function test_from_position(): void
    {
        $result = $this->collection->fromPosition(35);

        // Tokens à partir de la position 35:
        // Index 0: role (position 35)
        // Index 1: = (position 40)
        // Index 2: doctor (position 42) ← 3ème token
        // Index 3: ) (position 48)
        // Index 4: END (position 49)
        $this->assertCount(5, $result);
        $this->assertEquals('role', $result->first()?->value);

        $tokensArray = $result->toArray();
        $this->assertEquals('doctor', $tokensArray[2]?->value);
    }

    // ==================== VALUES TESTS ====================

    public function test_values(): void
    {
        $values = $this->collection->values();

        $this->assertCount(14, $values);
        $this->assertEquals('status', $values->first());
        $this->assertEquals('', $values->last());
    }

    // ==================== PURE OPERATORS TESTS ====================

    public function test_pure_comparison_operators(): void
    {
        $result = $this->collection->pureComparisonOperators();

        $this->assertCount(3, $result);
        $this->assertEquals('=', $result->first()?->value);
    }

    public function test_pure_logical_operators(): void
    {
        $result = $this->collection->pureLogicalOperators();

        $this->assertCount(2, $result);
        $this->assertEquals('AND', $result->first()?->value);
        $this->assertEquals('OR', $result->last()?->value);
    }

    // ==================== CHAIN FILTERS TESTS ====================

    public function test_chain_filters(): void
    {
        $result = $this->collection
            ->identifiers()
            ->withValues(['status', 'role']);

        $this->assertCount(3, $result);
        $this->assertEquals('status', $result->first()?->value);
    }

    public function test_chain_filters_with_operators(): void
    {
        $result = $this->collection
            ->operators()
            ->comparisonOperators();

        $this->assertCount(3, $result);
        $this->assertEquals('=', $result->first()?->value);
    }

    public function test_chain_filters_with_logical(): void
    {
        $result = $this->collection
            ->operators()
            ->logicalOperators();

        $this->assertCount(2, $result);
        $this->assertEquals('AND', $result->first()?->value);
        $this->assertEquals('OR', $result->last()?->value);
    }

    // ==================== EDGE CASES TESTS ====================

    public function test_empty_collection(): void
    {
        $emptyCollection = new TokenRecordCollection;

        $result = $emptyCollection->operators();

        $this->assertCount(0, $result);
        $this->assertEmpty($result->toArray());
    }

    public function test_empty_collection_values(): void
    {
        $emptyCollection = new TokenRecordCollection;

        $values = $emptyCollection->values();

        $this->assertCount(0, $values);
        $this->assertEmpty($values->toArray());
    }

    public function test_with_value_not_found(): void
    {
        $result = $this->collection->withValue('nonexistent');

        $this->assertCount(0, $result);
    }

    public function test_with_values_empty_array(): void
    {
        $result = $this->collection->withValues([]);

        $this->assertCount(0, $result);
    }

    public function test_of_type_end(): void
    {
        $result = $this->collection->ofType(TokenType::END);

        $this->assertCount(1, $result);
        $this->assertEquals('', $result->first()?->value);
        $this->assertEquals(49, $result->first()?->position);
    }

    public function test_from_position_start(): void
    {
        $result = $this->collection->fromPosition(0);

        $this->assertCount(14, $result);
        $this->assertEquals('status', $result->first()?->value);
    }

    public function test_from_position_end(): void
    {
        $result = $this->collection->fromPosition(49);

        $this->assertCount(1, $result);
        $this->assertEquals('', $result->first()?->value);
    }

    public function test_from_position_beyond_end(): void
    {
        $result = $this->collection->fromPosition(100);

        $this->assertCount(0, $result);
    }
}
