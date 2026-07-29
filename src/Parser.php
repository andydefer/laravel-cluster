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
            // Vérifier si c'est un chemin avec indices (ex: tags[0][0])
            if ($this->isIndexPath()) {
                $fullPath = $this->consumeIndexBrackets($key);

                return $this->parseCondition($fullPath);
            }

            // Vérifier si c'est une sous-condition vide (ex: addresses[])
            // ou un wildcard EXISTS (ex: addresses[])
            $tempPos = $this->position + 1;
            $tokens = $this->tokens->toArray();

            if ($tempPos < count($tokens) - 1) {
                $contentToken = $tokens[$tempPos] ?? null;
                $closeToken = $tokens[$tempPos + 1] ?? null;

                if ($contentToken && $contentToken->type === TokenType::IDENTIFIER &&
                    $contentToken->value === '*' &&
                    $closeToken && $closeToken->type === TokenType::SUB_CLOSE) {
                    // C'est un wildcard EXISTS ! (ex: addresses[])
                    $this->advancePosition(); // consomme [
                    $this->advancePosition(); // consomme *
                    $this->advancePosition(); // consomme ]

                    return new SubConditionNode($key, new ConditionNode('*', ComparisonOperator::EXISTS));
                }

                if ($closeToken && $closeToken->type === TokenType::SUB_CLOSE) {
                    // C'est une sous-condition vide ! (ex: addresses[])
                    $this->advancePosition(); // consomme [
                    $this->advancePosition(); // consomme ]

                    return new SubConditionNode($key, new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'true'));
                }
            }

            // C'est une sous-condition normale (ex: addresses[city=kinshasa])
            return $this->parseSubCondition($key);
        }

        return $this->parseCondition($key);
    }

    private function isIndexPath(): bool
    {
        $tempPos = $this->position;
        $tokens = $this->tokens->toArray();
        $tokensCount = count($tokens);

        if ($tempPos >= $tokensCount - 1) {
            return false;
        }

        $nextToken = $tokens[$tempPos + 1] ?? null;

        if (! $nextToken || $nextToken->type !== TokenType::IDENTIFIER) {
            return false;
        }

        $value = $nextToken->value;

        if (! is_numeric($value) && $value !== '*') {
            return false;
        }

        $closePos = $tempPos + 2;
        if ($closePos >= $tokensCount) {
            return false;
        }

        $closeToken = $tokens[$closePos] ?? null;

        if (! $closeToken || $closeToken->type !== TokenType::SUB_CLOSE) {
            return false;
        }

        $afterClose = $tokens[$closePos + 1] ?? null;

        if ($afterClose && $afterClose->type === TokenType::SUB_OPEN) {
            return true;
        }

        if ($afterClose && $afterClose->type === TokenType::OPERATOR) {
            return true;
        }

        if ($afterClose && $afterClose->type === TokenType::END) {
            return true;
        }

        return false;
    }

    private function consumeIndexBrackets(string $baseKey): string
    {
        $path = $baseKey;

        while ($this->position < $this->tokens->count() - 1) {
            $token = $this->getToken($this->position);

            if ($token && $token->type === TokenType::SUB_OPEN) {
                $path .= '[';
                $this->advancePosition();

                $contentToken = $this->getToken($this->position);
                if ($contentToken && $contentToken->type === TokenType::IDENTIFIER) {
                    $path .= $contentToken->value;
                    $this->advancePosition();
                }

                $closeToken = $this->getToken($this->position);
                if ($closeToken && $closeToken->type === TokenType::SUB_CLOSE) {
                    $path .= ']';
                    $this->advancePosition();
                }

                $nextToken = $this->getToken($this->position);
                if ($nextToken && $nextToken->type !== TokenType::SUB_OPEN) {
                    break;
                }
            } else {
                break;
            }
        }

        return $path;
    }

    private function parseSubCondition(string $parentKey): Node
    {
        // Consommer le SUB_OPEN
        $this->advancePosition();

        $nextToken = $this->getToken($this->position);

        // Vérifier si c'est une sous-condition vide (ex: addresses[])
        if ($nextToken && $nextToken->type === TokenType::SUB_CLOSE) {
            $this->advancePosition();

            return new SubConditionNode($parentKey, new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'true'));
        }

        // Vérifier si c'est un wildcard EXISTS (ex: addresses[])
        if ($nextToken && $nextToken->type === TokenType::IDENTIFIER && $nextToken->value === '*') {
            $this->advancePosition(); // consomme *
            $closeToken = $this->getToken($this->position);
            if ($closeToken && $closeToken->type === TokenType::SUB_CLOSE) {
                $this->advancePosition(); // consomme ]

                return new SubConditionNode($parentKey, new ConditionNode('*', ComparisonOperator::EXISTS));
            }
        }

        // Parser la condition à l'intérieur des crochets (ex: city=kinshasa)
        $condition = $this->parseExpression();

        // Vérifier qu'on a un SUB_CLOSE
        $closeToken = $this->getToken($this->position);
        if (! $closeToken || $closeToken->type !== TokenType::SUB_CLOSE) {
            throw new RuntimeException(sprintf(
                'Expected closing bracket ] at position %d, got: %s',
                $this->position,
                $closeToken ? $closeToken->value : 'EOF'
            ));
        }

        // Consommer le SUB_CLOSE
        $this->advancePosition();

        return new SubConditionNode($parentKey, $condition);
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
