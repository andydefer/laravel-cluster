<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster;

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;
use AndyDefer\LaravelCluster\Contracts\ParserInterface;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\TokenType;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\Node;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Records\TokenRecord;
use RuntimeException;

final class Parser implements ParserInterface
{
    private TokenRecordCollection $tokens;

    private int $position = 0;

    private array $cache = [];

    public function parse(string $query): Node
    {
        $cacheKey = md5($query);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $this->initializeParserState($query);
        $node = $this->parseExpression();

        $this->ensureNoRemainingTokens();

        $this->cache[$cacheKey] = $node;

        return $node;
    }

    private function initializeParserState(string $query): void
    {
        $this->tokens = (new Lexer)->tokenize($query);
        $this->position = 0;
    }

    private function ensureNoRemainingTokens(): void
    {
        if ($this->position < $this->tokens->count() - 1) {
            $remaining = [];
            $tokens = $this->tokens->toArray();
            for ($i = $this->position; $i < $this->tokens->count() - 1; $i++) {
                $remaining[] = $tokens[$i]->value;
            }
            throw new RuntimeException('Unexpected tokens after expression: '.implode(' ', $remaining));
        }
    }

    private function getToken(int $position): ?TokenRecord
    {
        $tokens = $this->tokens->toArray();

        return $tokens[$position] ?? null;
    }

    private function parseExpression(): Node
    {
        $left = $this->parseTerm();

        while ($this->position < $this->tokens->count() - 1) {
            $token = $this->getToken($this->position);

            if ($this->isLogicalOperator($token)) {
                $operator = LogicalOperator::fromValue($token->value);
                $this->advancePosition();
                $right = $this->parseTerm();
                $left = new GroupNode($operator, $left, $right);
            } else {
                break;
            }
        }

        return $left;
    }

    private function parseTerm(): Node
    {
        $token = $this->getToken($this->position);

        if (! $token) {
            throw new RuntimeException('Unexpected end of expression');
        }

        return match (true) {
            $this->isNotOperator($token) => $this->parseNotOperator(),
            $this->isExistsOperator($token) => $this->parseExistsOperator(),
            $this->isNotExistsOperator($token) => $this->parseNotExistsOperator(),
            $this->isOpeningParenthesis($token) => $this->parseGroupedExpression(),
            $token->type === TokenType::IDENTIFIER => $this->parseIdentifierTerm($token->value),
            $token->type === TokenType::SUB_OPEN => $this->parseSubConditionAlone(),
            default => throw new RuntimeException(
                sprintf('Invalid expression at position %d: %s', $this->position, $token->value)
            ),
        };
    }

    private function parseSubConditionAlone(): Node
    {
        // Consommer tout jusqu'à la fin
        while ($this->position < $this->tokens->count() - 1) {
            $this->advancePosition();
        }

        return new SubConditionNode('', new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'true'));
    }

    private function parseIdentifierTerm(string $key): Node
    {
        $this->advancePosition();

        $nextToken = $this->getToken($this->position);

        if ($nextToken && $nextToken->type === TokenType::SUB_OPEN) {
            return $this->parseSubCondition();
        }

        return $this->parseCondition($key);
    }

    private function parseSubCondition(): Node
    {
        $parentKey = '';
        $bracketCount = 0;

        while ($this->position < $this->tokens->count() - 1) {
            $token = $this->getToken($this->position);

            if ($token && $token->type === TokenType::SUB_OPEN) {
                $bracketCount++;
                $this->advancePosition();

                continue;
            }

            if ($token && $token->type === TokenType::SUB_CLOSE) {
                $bracketCount--;
                $this->advancePosition();
                if ($bracketCount === 0) {
                    break;
                }

                continue;
            }

            $this->advancePosition();
        }

        // Consommer tout ce qui reste (opérateur et valeur)
        while ($this->position < $this->tokens->count() - 1) {
            $token = $this->getToken($this->position);
            if ($token && $this->isLogicalOperator($token)) {
                break;
            }
            $this->advancePosition();
        }

        return new SubConditionNode($parentKey, new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'true'));
    }

    private function parseCondition(string $key): Node
    {
        $nextToken = $this->getToken($this->position);

        if (! $nextToken || $nextToken->type === TokenType::END) {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'true');
        }

        if ($nextToken->type === TokenType::PAREN && $nextToken->value === ')') {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'true');
        }

        if ($this->isLogicalOperator($nextToken)) {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'true');
        }

        if ($nextToken->type !== TokenType::OPERATOR) {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'true');
        }

        $operator = $nextToken->value;

        if ($operator === 'NOT') {
            return $this->parseNotCondition($key);
        }

        return $this->parseComparisonCondition($key, $operator);
    }

    private function parseNotCondition(string $key): Node
    {
        $this->advancePosition();
        $valueToken = $this->getToken($this->position);

        if (! $valueToken || $valueToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected identifier after NOT');
        }

        $this->advancePosition();

        return new ConditionNode($valueToken->value, ComparisonOperator::EQUAL, 'false');
    }

    private function parseComparisonCondition(string $key, string $operator): Node
    {
        $comparisonOperator = ComparisonOperator::fromValue($operator);

        if ($comparisonOperator === null) {
            throw new RuntimeException(sprintf(
                'Invalid operator "%s". Allowed: %s',
                $operator,
                implode(', ', ComparisonOperator::values())
            ));
        }

        $this->advancePosition();

        $valueToken = $this->getToken($this->position);

        if (! $valueToken || $valueToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected value after operator');
        }

        $this->advancePosition();

        return new ConditionNode($key, $comparisonOperator, $valueToken->value);
    }

    private function parseNotOperator(): Node
    {
        $this->advancePosition();
        $nextToken = $this->getToken($this->position);

        if (! $nextToken || $nextToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected identifier after NOT');
        }

        $this->advancePosition();

        return new ConditionNode($nextToken->value, ComparisonOperator::EQUAL, 'false');
    }

    private function parseExistsOperator(): Node
    {
        $this->advancePosition();
        $nextToken = $this->getToken($this->position);

        if (! $nextToken || $nextToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected identifier after *');
        }

        $this->advancePosition();

        return new ConditionNode($nextToken->value, ComparisonOperator::EXISTS);
    }

    private function parseNotExistsOperator(): Node
    {
        $this->advancePosition();
        $nextToken = $this->getToken($this->position);

        if (! $nextToken || $nextToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected identifier after #');
        }

        $this->advancePosition();

        return new ConditionNode($nextToken->value, ComparisonOperator::NOT_EXISTS);
    }

    private function parseGroupedExpression(): Node
    {
        $this->advancePosition();
        $node = $this->parseExpression();

        $nextToken = $this->getToken($this->position);

        if (! $nextToken || $nextToken->type !== TokenType::PAREN || $nextToken->value !== ')') {
            throw new RuntimeException('Missing closing parenthesis');
        }

        $this->advancePosition();

        return $node;
    }

    private function advancePosition(): void
    {
        $this->position++;
    }

    private function isLogicalOperator(?TokenRecord $token): bool
    {
        return $token !== null
            && $token->type === TokenType::OPERATOR
            && in_array($token->value, ['AND', 'OR'], true);
    }

    private function isNotOperator(TokenRecord $token): bool
    {
        return $token->type === TokenType::OPERATOR && $token->value === 'NOT';
    }

    private function isExistsOperator(TokenRecord $token): bool
    {
        return $token->type === TokenType::OPERATOR && $token->value === '*';
    }

    private function isNotExistsOperator(TokenRecord $token): bool
    {
        return $token->type === TokenType::OPERATOR && $token->value === '#';
    }

    private function isOpeningParenthesis(TokenRecord $token): bool
    {
        return $token->type === TokenType::PAREN && $token->value === '(';
    }
}
