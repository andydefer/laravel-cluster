<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster;

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;
use AndyDefer\LaravelCluster\Contracts\ParserInterface;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\TokenType;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\Node;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Records\TokenRecord;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use RuntimeException;

final class Parser implements ParserInterface
{
    private TokenRecordCollection $tokens;

    private int $position = 0;

    private array $cache = [];

    private SqlFunctionRegistry $functionRegistry;

    public function __construct()
    {
        $this->functionRegistry = app(SqlFunctionRegistry::class);
    }

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

        // Détection des fonctions SQL via le registre
        if ($token->type === TokenType::IDENTIFIER) {
            $functionName = strtoupper($token->value);
            if ($this->functionRegistry->has($functionName)) {
                return $this->parseFunction($functionName);
            }

            // Vérifier si c'est une fonction inconnue (suivi d'une parenthèse)
            $nextToken = $this->getToken($this->position + 1);
            if ($nextToken && $nextToken->type === TokenType::PAREN && $nextToken->value === '(') {
                throw new RuntimeException(sprintf('Unknown function "%s"', $functionName));
            }
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

    /**
     * Parse une fonction SQL (ex: COUNT(addresses) > 2)
     * ou COUNT(addresses) sans opérateur → COUNT > 0
     */
    private function parseFunction(string $functionName): Node
    {
        $functionName = strtoupper($functionName);

        // Vérifier que la fonction existe dans le registre
        if (! $this->functionRegistry->has($functionName)) {
            throw new RuntimeException(sprintf('Unknown function "%s"', $functionName));
        }

        // Consommer le nom de la fonction
        $this->advancePosition();

        // Vérifier la parenthèse ouvrante
        $openParen = $this->getToken($this->position);
        if (! $openParen || $openParen->type !== TokenType::PAREN || $openParen->value !== '(') {
            throw new RuntimeException('Expected opening parenthesis after function name');
        }
        $this->advancePosition();

        // Récupérer le chemin (l'argument)
        $pathToken = $this->getToken($this->position);
        if (! $pathToken || $pathToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected path argument for function');
        }
        $path = $pathToken->value;
        $this->advancePosition();

        // Vérifier la parenthèse fermante
        $closeParen = $this->getToken($this->position);
        if (! $closeParen || $closeParen->type !== TokenType::PAREN || $closeParen->value !== ')') {
            throw new RuntimeException('Expected closing parenthesis');
        }
        $this->advancePosition();

        // Avancer jusqu'au prochain token significatif (non-espace)
        $this->advanceToNextSignificantToken();

        // Vérifier l'opérateur et la valeur
        $operator = null;
        $value = null;

        $nextToken = $this->getToken($this->position);

        // Si on a un opérateur
        if ($nextToken && $nextToken->type === TokenType::OPERATOR) {
            $op = ComparisonOperator::fromValue($nextToken->value);
            if ($op !== null) {
                $operator = $op;
                $this->advancePosition();

                // Avancer jusqu'au prochain token significatif (non-espace)
                $this->advanceToNextSignificantToken();

                $valueToken = $this->getToken($this->position);
                if ($valueToken && $valueToken->type === TokenType::IDENTIFIER) {
                    $value = $valueToken->value;
                    $this->advancePosition();
                }
            }
        }

        // Si pas d'opérateur, on met COUNT > 0 par défaut
        if ($operator === null) {
            $operator = ComparisonOperator::GREATER_THAN;
            $value = '0';
        }

        return new FunctionNode($functionName, $path, $operator, $value);
    }

    /**
     * Avance la position jusqu'au prochain token significatif (non-espace)
     */
    private function advanceToNextSignificantToken(): void
    {
        while ($this->position < $this->tokens->count() - 1) {
            $token = $this->getToken($this->position);
            if ($token && ($token->value === ' ' || $token->type === TokenType::END)) {
                $this->advancePosition();
            } else {
                break;
            }
        }
    }

    /**
     * Parse une sous-condition seule (ex: addresses[] )
     */
    private function parseSubConditionAlone(): Node
    {
        // Consommer le SUB_OPEN
        $this->advancePosition();

        $nextToken = $this->getToken($this->position);

        // Si on a directement SUB_CLOSE, c'est une sous-condition vide
        if ($nextToken && $nextToken->type === TokenType::SUB_CLOSE) {
            $this->advancePosition();

            return new SubConditionNode('', new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'true'));
        }

        // Si c'est un wildcard * (ex: addresses[*])
        if ($nextToken && $nextToken->type === TokenType::IDENTIFIER && $nextToken->value === '*') {
            $this->advancePosition();
            $closeToken = $this->getToken($this->position);
            if ($closeToken && $closeToken->type === TokenType::SUB_CLOSE) {
                $this->advancePosition();

                return new SubConditionNode('', new ConditionNode('*', ComparisonOperator::EXISTS));
            }
        }

        // Sinon, c'est une sous-condition normale (ex: addresses[city=kinshasa])
        $condition = $this->parseExpression();

        $closeToken = $this->getToken($this->position);
        if (! $closeToken || $closeToken->type !== TokenType::SUB_CLOSE) {
            throw new RuntimeException(sprintf(
                'Expected closing bracket ] at position %d, got: %s',
                $this->position,
                $closeToken ? $closeToken->value : 'EOF'
            ));
        }

        $this->advancePosition();

        return new SubConditionNode('', $condition);
    }

    private function parseIdentifierTerm(string $key): Node
    {
        $this->advancePosition();

        $nextToken = $this->getToken($this->position);

        if ($nextToken && $nextToken->type === TokenType::SUB_OPEN) {
            if ($this->isIndexPath()) {
                $fullPath = $this->consumeIndexBrackets($key);

                return $this->parseCondition($fullPath);
            }

            $tempPos = $this->position + 1;
            $tokens = $this->tokens->toArray();

            if ($tempPos < count($tokens) - 1) {
                $contentToken = $tokens[$tempPos] ?? null;
                $closeToken = $tokens[$tempPos + 1] ?? null;

                if ($contentToken && $contentToken->type === TokenType::IDENTIFIER &&
                    $contentToken->value === '*' &&
                    $closeToken && $closeToken->type === TokenType::SUB_CLOSE) {
                    $this->advancePosition();
                    $this->advancePosition();
                    $this->advancePosition();

                    return new SubConditionNode($key, new ConditionNode('*', ComparisonOperator::EXISTS));
                }

                if ($closeToken && $closeToken->type === TokenType::SUB_CLOSE) {
                    $this->advancePosition();
                    $this->advancePosition();

                    return new SubConditionNode($key, new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'true'));
                }
            }

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
        $this->advancePosition();

        $nextToken = $this->getToken($this->position);

        if ($nextToken && $nextToken->type === TokenType::SUB_CLOSE) {
            $this->advancePosition();

            return new SubConditionNode($parentKey, new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'true'));
        }

        if ($nextToken && $nextToken->type === TokenType::IDENTIFIER && $nextToken->value === '*') {
            $this->advancePosition();
            $closeToken = $this->getToken($this->position);
            if ($closeToken && $closeToken->type === TokenType::SUB_CLOSE) {
                $this->advancePosition();

                return new SubConditionNode($parentKey, new ConditionNode('*', ComparisonOperator::EXISTS));
            }
        }

        $condition = $this->parseExpression();

        $closeToken = $this->getToken($this->position);
        if (! $closeToken || $closeToken->type !== TokenType::SUB_CLOSE) {
            throw new RuntimeException(sprintf(
                'Expected closing bracket ] at position %d, got: %s',
                $this->position,
                $closeToken ? $closeToken->value : 'EOF'
            ));
        }

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
