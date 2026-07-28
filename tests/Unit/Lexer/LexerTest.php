<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Lexer;

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;
use AndyDefer\LaravelCluster\Enums\TokenType;
use AndyDefer\LaravelCluster\Lexer;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    private Lexer $lexer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lexer = new Lexer;
    }

    // ==================== BASIC TESTS ====================

    public function test_tokenize_simple_equality(): void
    {
        $tokens = $this->lexer->tokenize('status=active');

        $this->assertCount(4, $tokens);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[0]->type);
        $this->assertEquals('status', $tokens->toArray()[0]->value);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[1]->type);
        $this->assertEquals('=', $tokens->toArray()[1]->value);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[2]->type);
        $this->assertEquals('active', $tokens->toArray()[2]->value);

        $this->assertEquals(TokenType::END, $tokens->toArray()[3]->type);
        $this->assertEquals('', $tokens->toArray()[3]->value);
    }

    public function test_tokenize_not_equality(): void
    {
        $tokens = $this->lexer->tokenize('status!=inactive');

        $this->assertCount(4, $tokens);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[0]->type);
        $this->assertEquals('status', $tokens->toArray()[0]->value);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[1]->type);
        $this->assertEquals('!=', $tokens->toArray()[1]->value);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[2]->type);
        $this->assertEquals('inactive', $tokens->toArray()[2]->value);
    }

    public function test_tokenize_strict_equality(): void
    {
        $tokens = $this->lexer->tokenize('age===25');

        $this->assertCount(4, $tokens);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[0]->type);
        $this->assertEquals('age', $tokens->toArray()[0]->value);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[1]->type);
        $this->assertEquals('===', $tokens->toArray()[1]->value);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[2]->type);
        $this->assertEquals('25', $tokens->toArray()[2]->value);
    }

    public function test_tokenize_strict_not_equality(): void
    {
        $tokens = $this->lexer->tokenize('age!==25');

        $this->assertCount(4, $tokens);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[0]->type);
        $this->assertEquals('age', $tokens->toArray()[0]->value);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[1]->type);
        $this->assertEquals('!==', $tokens->toArray()[1]->value);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[2]->type);
        $this->assertEquals('25', $tokens->toArray()[2]->value);
    }

    public function test_tokenize_greater_than(): void
    {
        $tokens = $this->lexer->tokenize('age>25');

        $this->assertCount(4, $tokens);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[1]->type);
        $this->assertEquals('>', $tokens->toArray()[1]->value);
    }

    public function test_tokenize_less_than(): void
    {
        $tokens = $this->lexer->tokenize('age<25');

        $this->assertCount(4, $tokens);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[1]->type);
        $this->assertEquals('<', $tokens->toArray()[1]->value);
    }

    public function test_tokenize_greater_than_or_equal(): void
    {
        $tokens = $this->lexer->tokenize('age>=25');

        $this->assertCount(4, $tokens);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[1]->type);
        $this->assertEquals('>=', $tokens->toArray()[1]->value);
    }

    public function test_tokenize_less_than_or_equal(): void
    {
        $tokens = $this->lexer->tokenize('age<=25');

        $this->assertCount(4, $tokens);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[1]->type);
        $this->assertEquals('<=', $tokens->toArray()[1]->value);
    }

    public function test_tokenize_spaceship(): void
    {
        $tokens = $this->lexer->tokenize('age<=>30');

        $this->assertCount(4, $tokens);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[1]->type);
        $this->assertEquals('<=>', $tokens->toArray()[1]->value);
    }

    // ==================== LOGICAL OPERATORS TESTS ====================

    public function test_tokenize_and(): void
    {
        $tokens = $this->lexer->tokenize('status=active & role=admin');

        $this->assertCount(8, $tokens);

        $found = false;
        foreach ($tokens->toArray() as $token) {
            if ($token->type === TokenType::OPERATOR && $token->value === 'AND') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function test_tokenize_or(): void
    {
        $tokens = $this->lexer->tokenize('status=active | role=admin');

        $this->assertCount(8, $tokens);

        $found = false;
        foreach ($tokens->toArray() as $token) {
            if ($token->type === TokenType::OPERATOR && $token->value === 'OR') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function test_tokenize_not(): void
    {
        $tokens = $this->lexer->tokenize('!lang_fr');

        $this->assertCount(3, $tokens);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[0]->type);
        $this->assertEquals('NOT', $tokens->toArray()[0]->value);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[1]->type);
        $this->assertEquals('lang_fr', $tokens->toArray()[1]->value);
    }

    // ==================== PARENTHESES TESTS ====================

    public function test_tokenize_parentheses(): void
    {
        $tokens = $this->lexer->tokenize('(status=active)');

        $this->assertCount(6, $tokens);

        $this->assertEquals(TokenType::PAREN, $tokens->toArray()[0]->type);
        $this->assertEquals('(', $tokens->toArray()[0]->value);

        $this->assertEquals(TokenType::PAREN, $tokens->toArray()[4]->type);
        $this->assertEquals(')', $tokens->toArray()[4]->value);
    }

    public function test_tokenize_nested_parentheses(): void
    {
        $tokens = $this->lexer->tokenize('(status=active & (role=admin | role=doctor))');

        $this->assertCount(16, $tokens);

        $parenCount = 0;
        foreach ($tokens->toArray() as $token) {
            if ($token->type === TokenType::PAREN && $token->value === '(') {
                $parenCount++;
            }
            if ($token->type === TokenType::PAREN && $token->value === ')') {
                $parenCount--;
            }
        }
        $this->assertEquals(0, $parenCount);
    }

    // ==================== IDENTIFIER TESTS ====================

    public function test_tokenize_identifier_with_underscore(): void
    {
        $tokens = $this->lexer->tokenize('lang_fr=true');

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[0]->type);
        $this->assertEquals('lang_fr', $tokens->toArray()[0]->value);
    }

    public function test_tokenize_identifier_with_hyphen(): void
    {
        $tokens = $this->lexer->tokenize('lang-fr=true');

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[0]->type);
        $this->assertEquals('lang-fr', $tokens->toArray()[0]->value);
    }

    public function test_tokenize_identifier_with_numbers(): void
    {
        $tokens = $this->lexer->tokenize('age25=30');

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[0]->type);
        $this->assertEquals('age25', $tokens->toArray()[0]->value);
    }

    // ==================== SPACE TESTS ====================

    public function test_tokenize_with_spaces(): void
    {
        $tokens1 = $this->lexer->tokenize('status=active & role=admin');
        $tokens2 = $this->lexer->tokenize('status=active&role=admin');

        $this->assertCount(count($tokens1->toArray()), $tokens2->toArray());

        $tokens1Array = $tokens1->toArray();
        $tokens2Array = $tokens2->toArray();

        for ($i = 0; $i < count($tokens1Array); $i++) {
            $this->assertEquals($tokens1Array[$i]->type, $tokens2Array[$i]->type);
            $this->assertEquals($tokens1Array[$i]->value, $tokens2Array[$i]->value);
        }
    }

    public function test_tokenize_multiple_spaces(): void
    {
        $tokens = $this->lexer->tokenize('status  =  active');

        $this->assertCount(4, $tokens);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[0]->type);
        $this->assertEquals('status', $tokens->toArray()[0]->value);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[1]->type);
        $this->assertEquals('=', $tokens->toArray()[1]->value);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[2]->type);
        $this->assertEquals('active', $tokens->toArray()[2]->value);
    }

    // ==================== COMPLEX EXPRESSION TESTS ====================

    public function test_tokenize_complex_expression(): void
    {
        $tokens = $this->lexer->tokenize('(status=active & !lang_fr) | (role=admin & age>=25)');

        // Comptons : 2 parenthèses ouvertes + 2 fermées + identifiants + opérateurs
        // (status=active & !lang_fr) | (role=admin & age>=25)
        // Tokens: (, status, =, active, AND, !, lang_fr, ), OR, (, role, =, admin, AND, age, >=, 25, ), END
        // Total: 19 tokens
        $this->assertCount(19, $tokens);

        $operators = [];
        foreach ($tokens->toArray() as $token) {
            if ($token->type === TokenType::OPERATOR) {
                $operators[] = $token->value;
            }
        }

        $this->assertContains('=', $operators);
        $this->assertContains('AND', $operators);
        $this->assertContains('NOT', $operators);
        $this->assertContains('OR', $operators);
        $this->assertContains('>=', $operators);
    }

    public function test_tokenize_with_all_operators(): void
    {
        $tokens = $this->lexer->tokenize('a=1 & b==2 & c===3 & d!=4 & e!==5 & f<6 & g<=7 & h>8 & i>=9 & j<=>10');

        $operators = [];
        foreach ($tokens->toArray() as $token) {
            if ($token->type === TokenType::OPERATOR) {
                $operators[] = $token->value;
            }
        }

        $this->assertContains('=', $operators);
        $this->assertContains('==', $operators);
        $this->assertContains('===', $operators);
        $this->assertContains('!=', $operators);
        $this->assertContains('!==', $operators);
        $this->assertContains('<', $operators);
        $this->assertContains('<=', $operators);
        $this->assertContains('>', $operators);
        $this->assertContains('>=', $operators);
        $this->assertContains('<=>', $operators);
        $this->assertContains('AND', $operators);
    }

    // ==================== POSITION TESTS ====================

    public function test_tokenize_positions(): void
    {
        $tokens = $this->lexer->tokenize('status=active');

        $this->assertEquals(6, $tokens->toArray()[0]->position);
        $this->assertEquals(6, $tokens->toArray()[1]->position);
        $this->assertEquals(13, $tokens->toArray()[2]->position);
        $this->assertEquals(13, $tokens->toArray()[3]->position);
    }

    // ==================== EDGE CASES TESTS ====================

    public function test_tokenize_empty_string(): void
    {
        $tokens = $this->lexer->tokenize('');

        $this->assertCount(1, $tokens);
        $this->assertEquals(TokenType::END, $tokens->toArray()[0]->type);
        $this->assertEquals('', $tokens->toArray()[0]->value);
        $this->assertEquals(0, $tokens->toArray()[0]->position);
    }

    public function test_tokenize_only_whitespace(): void
    {
        $tokens = $this->lexer->tokenize('   ');

        $this->assertCount(1, $tokens);
        $this->assertEquals(TokenType::END, $tokens->toArray()[0]->type);
    }

    public function test_tokenize_single_identifier(): void
    {
        $tokens = $this->lexer->tokenize('lang_fr');

        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[0]->type);
        $this->assertEquals('lang_fr', $tokens->toArray()[0]->value);
        $this->assertEquals(TokenType::END, $tokens->toArray()[1]->type);
    }

    public function test_tokenize_single_parenthesis(): void
    {
        $tokens = $this->lexer->tokenize('(');

        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::PAREN, $tokens->toArray()[0]->type);
        $this->assertEquals('(', $tokens->toArray()[0]->value);
    }

    // ==================== ERROR TESTS ====================

    public function test_tokenize_invalid_character(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid character "@" at position 0');

        $this->lexer->tokenize('@invalid');
    }

    public function test_tokenize_invalid_character_middle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->lexer->tokenize('status=active@role');
    }

    // ==================== COLLECTION TESTS ====================

    public function test_tokenize_returns_token_record_collection(): void
    {
        $tokens = $this->lexer->tokenize('status=active');

        $this->assertInstanceOf(TokenRecordCollection::class, $tokens);
    }

    public function test_tokenize_collection_methods(): void
    {
        $tokens = $this->lexer->tokenize('status=active & role=admin');

        $identifiers = $tokens->identifiers();
        $this->assertCount(4, $identifiers);

        $operators = $tokens->operators();
        $this->assertCount(3, $operators);

        $withoutEnd = $tokens->withoutEnd();
        $this->assertCount(7, $withoutEnd);
    }

    // ==================== OPERATOR PRECEDENCE TESTS ====================

    public function test_tokenize_operator_precedence(): void
    {
        $tokens = $this->lexer->tokenize('a= b== c===');

        $this->assertEquals('=', $tokens->toArray()[1]->value);
        $this->assertEquals('==', $tokens->toArray()[3]->value);
        $this->assertEquals('===', $tokens->toArray()[5]->value);
    }

    public function test_tokenize_not_operator(): void
    {
        $tokens = $this->lexer->tokenize('!lang_fr & !lang_en');

        // Tokens: NOT, lang_fr, AND, NOT, lang_en, END
        $this->assertEquals('NOT', $tokens->toArray()[0]->value);
        $this->assertEquals('AND', $tokens->toArray()[2]->value);
        $this->assertEquals('NOT', $tokens->toArray()[3]->value);
    }

    // ==================== EXISTS / NOT_EXISTS OPERATORS TESTS ====================

    public function test_tokenize_exists_operator(): void
    {
        $tokens = $this->lexer->tokenize('*name');

        $this->assertCount(3, $tokens);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[0]->type);
        $this->assertEquals('*', $tokens->toArray()[0]->value);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[1]->type);
        $this->assertEquals('name', $tokens->toArray()[1]->value);
    }

    public function test_tokenize_not_exists_operator(): void
    {
        $tokens = $this->lexer->tokenize('#profile');

        $this->assertCount(3, $tokens);

        $this->assertEquals(TokenType::OPERATOR, $tokens->toArray()[0]->type);
        $this->assertEquals('#', $tokens->toArray()[0]->value);

        $this->assertEquals(TokenType::IDENTIFIER, $tokens->toArray()[1]->type);
        $this->assertEquals('profile', $tokens->toArray()[1]->value);
    }

    public function test_tokenize_exists_with_and_condition(): void
    {
        $tokens = $this->lexer->tokenize('*verified & status=active');

        $this->assertCount(7, $tokens);

        $foundExists = false;
        $foundAnd = false;
        foreach ($tokens->toArray() as $token) {
            if ($token->type === TokenType::OPERATOR && $token->value === '*') {
                $foundExists = true;
            }
            if ($token->type === TokenType::OPERATOR && $token->value === 'AND') {
                $foundAnd = true;
            }
        }
        $this->assertTrue($foundExists);
        $this->assertTrue($foundAnd);
    }

    public function test_tokenize_not_exists_with_or_condition(): void
    {
        $tokens = $this->lexer->tokenize('#lang_es | status=active');

        $this->assertCount(7, $tokens);

        $foundNotExists = false;
        $foundOr = false;
        foreach ($tokens->toArray() as $token) {
            if ($token->type === TokenType::OPERATOR && $token->value === '#') {
                $foundNotExists = true;
            }
            if ($token->type === TokenType::OPERATOR && $token->value === 'OR') {
                $foundOr = true;
            }
        }
        $this->assertTrue($foundNotExists);
        $this->assertTrue($foundOr);
    }

    public function test_tokenize_complex_with_exists(): void
    {
        $tokens = $this->lexer->tokenize('(*lang_fr | #lang_en) & age>=25');

        $this->assertCount(12, $tokens);

        $foundExists = false;
        $foundNotExists = false;
        foreach ($tokens->toArray() as $token) {
            if ($token->type === TokenType::OPERATOR && $token->value === '*') {
                $foundExists = true;
            }
            if ($token->type === TokenType::OPERATOR && $token->value === '#') {
                $foundNotExists = true;
            }
        }
        $this->assertTrue($foundExists);
        $this->assertTrue($foundNotExists);
    }
}
