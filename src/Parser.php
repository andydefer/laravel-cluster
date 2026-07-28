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
use AndyDefer\LaravelCluster\Records\TokenRecord;

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

        $this->tokens = (new Lexer)->tokenize($query);
        $this->position = 0;

        $node = $this->parseExpression();

        if ($this->position < $this->tokens->count() - 1) {
            throw new \RuntimeException('Unexpected tokens after expression');
        }

        $this->cache[$cacheKey] = $node;

        return $node;
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

            if ($token && $token->type === TokenType::OPERATOR && in_array($token->value, ['AND', 'OR'], true)) {
                $operator = LogicalOperator::fromValue($token->value);
                $this->position++;
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
            throw new \RuntimeException('Unexpected end of expression');
        }

        // NOT suivi d'un identifiant: "!lang_fr" → "lang_fr=false"
        if ($token->type === TokenType::OPERATOR && $token->value === 'NOT') {
            $nextToken = $this->getToken($this->position + 1);

            if ($nextToken && $nextToken->type === TokenType::IDENTIFIER) {
                $this->position += 2;

                return new ConditionNode($nextToken->value, ComparisonOperator::EQUAL, 'false');
            }

            throw new \RuntimeException('Expected identifier after NOT');
        }

        // EXISTS suivi d'un identifiant: "*name" → vérifier l'existence
        if ($token->type === TokenType::OPERATOR && $token->value === '*') {
            $nextToken = $this->getToken($this->position + 1);

            if ($nextToken && $nextToken->type === TokenType::IDENTIFIER) {
                $this->position += 2;

                return new ConditionNode($nextToken->value, ComparisonOperator::EXISTS);
            }

            throw new \RuntimeException('Expected identifier after *');
        }

        // NOT_EXISTS suivi d'un identifiant: "#name" → vérifier l'absence
        if ($token->type === TokenType::OPERATOR && $token->value === '#') {
            $nextToken = $this->getToken($this->position + 1);

            if ($nextToken && $nextToken->type === TokenType::IDENTIFIER) {
                $this->position += 2;

                return new ConditionNode($nextToken->value, ComparisonOperator::NOT_EXISTS);
            }

            throw new \RuntimeException('Expected identifier after #');
        }

        if ($token->type === TokenType::PAREN && $token->value === '(') {
            $this->position++;
            $node = $this->parseExpression();

            $next = $this->getToken($this->position);

            if (! $next || $next->type !== TokenType::PAREN || $next->value !== ')') {
                throw new \RuntimeException('Missing closing parenthesis');
            }

            $this->position++;

            return $node;
        }

        if ($token->type === TokenType::IDENTIFIER) {
            return $this->parseCondition($token->value);
        }

        throw new \RuntimeException(sprintf('Invalid expression at position %d', $this->position));
    }

    private function parseCondition(string $key): Node
    {
        $this->position++;

        $next = $this->getToken($this->position);

        // Condition simple: "lang_fr" → "lang_fr=true"
        if (! $next || $next->type !== TokenType::OPERATOR) {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'true');
        }

        $operator = $next->value;

        // Vérifier si c'est un opérateur logique (AND, OR)
        if (in_array($operator, ['AND', 'OR'], true)) {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'true');
        }

        // NOT suivi d'un identifiant: "!lang_fr" → "lang_fr=false"
        if ($operator === 'NOT') {
            $valueToken = $this->getToken($this->position + 1);

            if (! $valueToken || $valueToken->type !== TokenType::IDENTIFIER) {
                throw new \RuntimeException('Expected identifier after NOT');
            }

            $this->position += 2;

            return new ConditionNode($valueToken->value, ComparisonOperator::EQUAL, 'false');
        }

        // Opérateur EXISTS: "*name" → vérifier l'existence de la clé
        if ($operator === '*') {
            $this->position++;

            return new ConditionNode($key, ComparisonOperator::EXISTS);
        }

        // Opérateur NOT_EXISTS: "#profile" → vérifier l'absence de la clé
        if ($operator === '#') {
            $this->position++;

            return new ConditionNode($key, ComparisonOperator::NOT_EXISTS);
        }

        // Opérateur de comparaison
        $comparisonOperator = ComparisonOperator::fromValue($operator);

        if ($comparisonOperator === null) {
            throw new \RuntimeException(sprintf(
                'Invalid operator "%s". Allowed: %s',
                $operator,
                implode(', ', ComparisonOperator::values())
            ));
        }

        $this->position++;

        $valueToken = $this->getToken($this->position);

        if (! $valueToken || $valueToken->type !== TokenType::IDENTIFIER) {
            throw new \RuntimeException('Expected value after operator');
        }

        $this->position++;

        return new ConditionNode($key, $comparisonOperator, $valueToken->value);
    }
}
