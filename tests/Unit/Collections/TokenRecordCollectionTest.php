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

    // ==================== SUB BRACKET TESTS ====================

    public function test_sub_opens(): void
    {
        // Créer une collection avec des tokens de sub_open
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::SUB_OPEN, '[', 50));
        $collection->add(new TokenRecord(TokenType::SUB_OPEN, '[', 70));

        $result = $collection->subOpens();

        $this->assertCount(2, $result);
        foreach ($result as $token) {
            $this->assertEquals(TokenType::SUB_OPEN, $token->type);
            $this->assertEquals('[', $token->value);
        }
    }

    public function test_sub_closes(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::SUB_CLOSE, ']', 68));
        $collection->add(new TokenRecord(TokenType::SUB_CLOSE, ']', 74));

        $result = $collection->subCloses();

        $this->assertCount(2, $result);
        foreach ($result as $token) {
            $this->assertEquals(TokenType::SUB_CLOSE, $token->type);
            $this->assertEquals(']', $token->value);
        }
    }

    public function test_sub_opens_returns_empty_for_no_brackets(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::IDENTIFIER, 'status', 0));
        $collection->add(new TokenRecord(TokenType::OPERATOR, '=', 7));

        $result = $collection->subOpens();

        $this->assertCount(0, $result);
    }

    public function test_chain_with_sub_opens(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::SUB_OPEN, '[', 50));
        $collection->add(new TokenRecord(TokenType::IDENTIFIER, 'city', 52));

        $result = $collection->subOpens()->withValue('[');

        $this->assertCount(1, $result);
        $this->assertEquals('[', $result->first()?->value);
    }

    public function test_chain_sub_opens_then_identifiers(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::SUB_OPEN, '[', 50));
        $collection->add(new TokenRecord(TokenType::IDENTIFIER, 'city', 52));
        $collection->add(new TokenRecord(TokenType::SUB_CLOSE, ']', 54));

        $result = $collection->subOpens();

        $this->assertCount(1, $result);
        $this->assertEquals(TokenType::SUB_OPEN, $result->first()?->type);
    }

    // ==================== SUB BRACKET TESTS MANQUANTS ====================

    public function test_sub_closes_returns_empty_for_no_brackets(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::IDENTIFIER, 'status', 0));
        $collection->add(new TokenRecord(TokenType::OPERATOR, '=', 7));

        $result = $collection->subCloses();

        $this->assertCount(0, $result);
    }

    public function test_chain_with_sub_closes(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::SUB_CLOSE, ']', 54));

        $result = $collection->subCloses()->withValue(']');

        $this->assertCount(1, $result);
        $this->assertEquals(']', $result->first()?->value);
    }

    public function test_of_type_sub_open(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::SUB_OPEN, '[', 50));
        $collection->add(new TokenRecord(TokenType::IDENTIFIER, 'city', 52));

        $result = $collection->ofType(TokenType::SUB_OPEN);

        $this->assertCount(1, $result);
        $this->assertEquals(TokenType::SUB_OPEN, $result->first()?->type);
    }

    public function test_of_type_sub_close(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::SUB_CLOSE, ']', 54));
        $collection->add(new TokenRecord(TokenType::IDENTIFIER, 'city', 52));

        $result = $collection->ofType(TokenType::SUB_CLOSE);

        $this->assertCount(1, $result);
        $this->assertEquals(TokenType::SUB_CLOSE, $result->first()?->type);
    }

    public function test_sub_opens_with_multiple_tokens(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::SUB_OPEN, '[', 50));
        $collection->add(new TokenRecord(TokenType::IDENTIFIER, '0', 52));
        $collection->add(new TokenRecord(TokenType::SUB_OPEN, '[', 55));
        $collection->add(new TokenRecord(TokenType::IDENTIFIER, '1', 57));
        $collection->add(new TokenRecord(TokenType::SUB_CLOSE, ']', 60));

        $result = $collection->subOpens();

        $this->assertCount(2, $result);
        $this->assertEquals('[', $result->first()?->value);
        $this->assertEquals('[', $result->last()?->value);
    }

    public function test_sub_closes_with_multiple_tokens(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::SUB_OPEN, '[', 50));
        $collection->add(new TokenRecord(TokenType::IDENTIFIER, '0', 52));
        $collection->add(new TokenRecord(TokenType::SUB_CLOSE, ']', 54));
        $collection->add(new TokenRecord(TokenType::SUB_CLOSE, ']', 56));

        $result = $collection->subCloses();

        $this->assertCount(2, $result);
        $this->assertEquals(']', $result->first()?->value);
        $this->assertEquals(']', $result->last()?->value);
    }

    public function test_chain_sub_opens_and_sub_closes(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::SUB_OPEN, '[', 50));
        $collection->add(new TokenRecord(TokenType::IDENTIFIER, '0', 52));
        $collection->add(new TokenRecord(TokenType::SUB_CLOSE, ']', 54));

        $subOpens = $collection->subOpens();
        $subCloses = $collection->subCloses();

        $this->assertCount(1, $subOpens);
        $this->assertCount(1, $subCloses);
    }

    public function test_values_with_sub_brackets(): void
    {
        $collection = new TokenRecordCollection;
        $collection->add(new TokenRecord(TokenType::SUB_OPEN, '[', 50));
        $collection->add(new TokenRecord(TokenType::IDENTIFIER, '0', 52));
        $collection->add(new TokenRecord(TokenType::SUB_CLOSE, ']', 54));

        $values = $collection->values();

        $this->assertCount(3, $values);
        $this->assertEquals('[', $values->first());
        $this->assertEquals(']', $values->last());
    }

    public function test_empty_collection_sub_opens(): void
    {
        $emptyCollection = new TokenRecordCollection;

        $result = $emptyCollection->subOpens();

        $this->assertCount(0, $result);
        $this->assertEmpty($result->toArray());
    }

    public function test_empty_collection_sub_closes(): void
    {
        $emptyCollection = new TokenRecordCollection;

        $result = $emptyCollection->subCloses();

        $this->assertCount(0, $result);
        $this->assertEmpty($result->toArray());
    }
}
