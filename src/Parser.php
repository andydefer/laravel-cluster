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

/**
 * Parser for converting token streams into an Abstract Syntax Tree (AST).
 *
 * This class transforms tokens from the lexer into a tree of Node objects
 * representing the query structure. It handles:
 * - Logical operators (AND, OR)
 * - Comparison operators (=, !=, >, <, >=, <=, LIKE, etc.)
 * - Functions (COUNT, SUM, AVG, CONTAINS, etc.)
 * - Sub-conditions (addresses[city=Kinshasa])
 * - Parentheses for grouping
 * - Special operators (NOT, EXISTS (*), NOT_EXISTS (#))
 *
 * @example
 * $parser = new Parser();
 * $ast = $parser->parse('status=active & COUNT(addresses) > 2');
 * // GroupNode containing ConditionNode and FunctionNode
 * @example
 * $ast = $parser->parse('addresses[city=Kinshasa]');
 * // SubConditionNode with ConditionNode inside
 * @example
 * $ast = $parser->parse('CONTAINS(languages, fr)');
 * // FunctionNode with CONTAINS
 */
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

    /**
     * Parses a query string into an Abstract Syntax Tree.
     *
     * @param  string  $query  The query string to parse
     * @return Node The root node of the AST
     *
     * @throws RuntimeException When the query is invalid
     */
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

    /**
     * Initializes the parser state for a new parse.
     *
     * @param  string  $query  The query to parse
     */
    private function initializeParserState(string $query): void
    {
        $this->tokens = (new Lexer)->tokenize($query);
        $this->position = 0;
    }

    /**
     * Ensures no unexpected tokens remain after parsing.
     *
     * @throws RuntimeException When unexpected tokens remain
     */
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

    /**
     * Retrieves a token at a specific position.
     *
     * @param  int  $position  The position to retrieve
     * @return TokenRecord|null The token, or null if out of bounds
     */
    private function getToken(int $position): ?TokenRecord
    {
        $tokens = $this->tokens->toArray();

        return $tokens[$position] ?? null;
    }

    /**
     * Parses an expression with logical operators.
     *
     * @return Node The parsed expression node
     */
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

    /**
     * Parses a single term (condition, function, or grouped expression).
     *
     *
     * @return Node The parsed term node
     *
     * @throws RuntimeException When the term is invalid
     */
    private function parseTerm(): Node
    {
        $token = $this->getToken($this->position);

        if (! $token) {
            throw new RuntimeException('Unexpected end of expression');
        }

        if ($token->type === TokenType::IDENTIFIER) {
            $functionName = strtoupper($token->value);
            if ($this->functionRegistry->has($functionName)) {
                return $this->parseFunction($functionName);
            }

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
     * Parses a SQL function expression.
     *
     * @param  string  $functionName  The name of the function
     * @return FunctionNode The parsed function node
     *
     * @throws RuntimeException When the function syntax is invalid
     */
    private function parseFunction(string $functionName): Node
    {
        $functionName = strtoupper($functionName);

        if (! $this->functionRegistry->has($functionName)) {
            throw new RuntimeException(sprintf('Unknown function "%s"', $functionName));
        }

        $this->advancePosition();

        $openParen = $this->getToken($this->position);
        if (! $openParen || $openParen->type !== TokenType::PAREN || $openParen->value !== '(') {
            throw new RuntimeException('Expected opening parenthesis after function name');
        }
        $this->advancePosition();

        // Read function arguments (separated by commas)
        $args = [];
        $currentArg = '';
        $foundClosingParen = false;
        $parenDepth = 0;

        while ($this->position < $this->tokens->count() - 1) {
            $token = $this->getToken($this->position);

            // Handle nested parentheses
            if ($token->type === TokenType::PAREN && $token->value === '(') {
                $parenDepth++;
                $currentArg .= $token->value;
                $this->advancePosition();

                continue;
            }

            if ($token->type === TokenType::PAREN && $token->value === ')') {
                if ($parenDepth > 0) {
                    $parenDepth--;
                    $currentArg .= $token->value;
                    $this->advancePosition();

                    continue;
                }

                // Closing parenthesis for the function
                if (! empty($currentArg)) {
                    $args[] = trim($currentArg);
                }
                $foundClosingParen = true;
                $this->advancePosition();
                break;
            }

            // Comma separator - only at depth 0
            if ($parenDepth === 0 && $token->type === TokenType::OPERATOR && $token->value === ',') {
                $args[] = trim($currentArg);
                $currentArg = '';
                $this->advancePosition();

                continue;
            }

            // For all other tokens, add to current argument
            $currentArg .= $token->value;
            $this->advancePosition();
        }

        if (! $foundClosingParen) {
            throw new RuntimeException('Expected closing parenthesis');
        }

        // ✅ VALIDATION GÉNÉRIQUE : Vérifier le nombre minimum d'arguments
        $minArgs = $this->functionRegistry->getMinArgs($functionName) ?? 1;
        if (count($args) < $minArgs) {
            throw new RuntimeException(sprintf(
                'Function "%s" expects at least %d argument%s, %d given',
                $functionName,
                $minArgs,
                $minArgs > 1 ? 's' : '',
                count($args)
            ));
        }

        // ✅ VALIDATION GÉNÉRIQUE : Vérifier le nombre maximum d'arguments
        $maxArgs = $this->functionRegistry->getMaxArgs($functionName) ?? PHP_INT_MAX;
        if (count($args) > $maxArgs) {
            throw new RuntimeException(sprintf(
                'Function "%s" expects at most %d argument%s, %d given',
                $functionName,
                $maxArgs,
                $maxArgs > 1 ? 's' : '',
                count($args)
            ));
        }

        // ✅ VALIDATION GÉNÉRIQUE : Vérifier les arguments via validateArgs
        if (! $this->functionRegistry->validateArgs($functionName, $args)) {
            throw new RuntimeException(sprintf(
                'Invalid arguments for function "%s"',
                $functionName
            ));
        }

        // Le premier argument est le path
        $path = $args[0] ?? '';

        // Pour les fonctions avec plusieurs arguments, le deuxième est la valeur de comparaison
        $comparisonValue = null;
        if (count($args) >= 2) {
            $comparisonValue = $args[1];
        }

        $this->advanceToNextSignificantToken();

        $operator = null;

        $nextToken = $this->getToken($this->position);

        if ($nextToken && $nextToken->type === TokenType::OPERATOR) {
            $op = ComparisonOperator::fromValue($nextToken->value);
            if ($op !== null) {
                $operator = $op;
                $this->advancePosition();

                $this->advanceToNextSignificantToken();

                $valueToken = $this->getToken($this->position);
                if ($valueToken && $valueToken->type === TokenType::IDENTIFIER) {
                    // Si un opérateur est présent, la valeur de comparaison est après l'opérateur
                    $comparisonValue = $valueToken->value;
                    $this->advancePosition();
                }
            }
        }

        // Si pas d'opérateur, on utilise GREATER_THAN avec 0 comme valeur par défaut
        if ($operator === null) {
            $operator = ComparisonOperator::GREATER_THAN;
            $comparisonValue = '0';
        }

        return new FunctionNode($functionName, $path, $operator, $comparisonValue, $args);
    }

    /**
     * Advances the position to the next significant (non-space) token.
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
     * Parses a standalone sub-condition (e.g., addresses[] or addresses[city=kinshasa]).
     *
     *
     * @return SubConditionNode The parsed sub-condition node
     *
     * @throws RuntimeException When the sub-condition syntax is invalid
     */
    private function parseSubConditionAlone(): Node
    {
        $this->advancePosition();

        $nextToken = $this->getToken($this->position);

        if ($nextToken && $nextToken->type === TokenType::SUB_CLOSE) {
            $this->advancePosition();

            return new SubConditionNode('', new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'yes'));
        }

        if ($nextToken && $nextToken->type === TokenType::IDENTIFIER && $nextToken->value === '*') {
            $this->advancePosition();
            $closeToken = $this->getToken($this->position);
            if ($closeToken && $closeToken->type === TokenType::SUB_CLOSE) {
                $this->advancePosition();

                return new SubConditionNode('', new ConditionNode('*', ComparisonOperator::EXISTS));
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

        return new SubConditionNode('', $condition);
    }

    /**
     * Parses an identifier term (key or path).
     *
     * @param  string  $key  The identifier
     * @return Node The parsed node
     */
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

                    return new SubConditionNode($key, new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'yes'));
                }
            }

            return $this->parseSubCondition($key);
        }

        return $this->parseCondition($key);
    }

    /**
     * Determines if the current position is an index path (e.g., tags[0][0]).
     *
     * @return bool True if the current position is an index path
     */
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

    /**
     * Consumes index brackets and builds a full path (e.g., tags[0][0]).
     *
     * @param  string  $baseKey  The base key
     * @return string The full path with brackets
     */
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

    /**
     * Parses a sub-condition with a parent key (e.g., addresses[city=kinshasa]).
     *
     * @param  string  $parentKey  The parent key
     * @return SubConditionNode The parsed sub-condition node
     *
     * @throws RuntimeException When the sub-condition syntax is invalid
     */
    private function parseSubCondition(string $parentKey): Node
    {
        $this->advancePosition();

        $nextToken = $this->getToken($this->position);

        if ($nextToken && $nextToken->type === TokenType::SUB_CLOSE) {
            $this->advancePosition();

            return new SubConditionNode($parentKey, new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'yes'));
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

    /**
     * Parses a condition (key + operator + value).
     *
     * @param  string  $key  The key of the condition
     * @return ConditionNode The parsed condition node
     */
    private function parseCondition(string $key): Node
    {
        $nextToken = $this->getToken($this->position);

        if (! $nextToken || $nextToken->type === TokenType::END) {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'yes');
        }

        if ($nextToken->type === TokenType::PAREN && $nextToken->value === ')') {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'yes');
        }

        if ($this->isLogicalOperator($nextToken)) {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'yes');
        }

        if ($nextToken->type !== TokenType::OPERATOR) {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'yes');
        }

        $operator = $nextToken->value;

        if ($operator === 'NOT') {
            return $this->parseNotCondition($key);
        }

        return $this->parseComparisonCondition($key, $operator);
    }

    /**
     * Parses a NOT condition (e.g., NOT status).
     *
     * @param  string  $key  The key of the condition
     * @return ConditionNode The parsed condition node
     *
     * @throws RuntimeException When the NOT condition syntax is invalid
     */
    private function parseNotCondition(string $key): Node
    {
        $this->advancePosition();
        $valueToken = $this->getToken($this->position);

        if (! $valueToken || $valueToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected identifier after NOT');
        }

        $this->advancePosition();

        // NOT key means key should be 'no'
        return new ConditionNode($valueToken->value, ComparisonOperator::EQUAL, 'no');
    }

    /**
     * Parses a comparison condition (e.g., status=active).
     *
     * @param  string  $key  The key of the condition
     * @param  string  $operator  The comparison operator
     * @return ConditionNode The parsed condition node
     *
     * @throws RuntimeException When the comparison syntax is invalid
     */
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

        $value = $valueToken->value;
        $this->advancePosition();

        return new ConditionNode($key, $comparisonOperator, $value);
    }

    /**
     * Parses a NOT operator at the beginning of a term.
     *
     *
     * @return ConditionNode The parsed condition node
     *
     * @throws RuntimeException When the NOT operator syntax is invalid
     */
    private function parseNotOperator(): Node
    {
        $this->advancePosition();
        $nextToken = $this->getToken($this->position);

        if (! $nextToken || $nextToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected identifier after NOT');
        }

        $this->advancePosition();

        // NOT key means key should be 'no'
        return new ConditionNode($nextToken->value, ComparisonOperator::EQUAL, 'no');
    }

    /**
     * Parses an EXISTS operator (*).
     *
     *
     * @return ConditionNode The parsed condition node
     *
     * @throws RuntimeException When the EXISTS operator syntax is invalid
     */
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

    /**
     * Parses a NOT_EXISTS operator (#).
     *
     *
     * @return ConditionNode The parsed condition node
     *
     * @throws RuntimeException When the NOT_EXISTS operator syntax is invalid
     */
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

    /**
     * Parses a grouped expression inside parentheses.
     *
     *
     * @return Node The parsed grouped expression node
     *
     * @throws RuntimeException When the grouped expression syntax is invalid
     */
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

    /**
     * Advances the current position by one.
     */
    private function advancePosition(): void
    {
        $this->position++;
    }

    /**
     * Determines if a token is a logical operator (AND or OR).
     *
     * @param  TokenRecord|null  $token  The token to check
     * @return bool True if the token is a logical operator
     */
    private function isLogicalOperator(?TokenRecord $token): bool
    {
        return $token !== null
            && $token->type === TokenType::OPERATOR
            && in_array($token->value, ['AND', 'OR'], true);
    }

    /**
     * Determines if a token is a NOT operator.
     *
     * @param  TokenRecord  $token  The token to check
     * @return bool True if the token is a NOT operator
     */
    private function isNotOperator(TokenRecord $token): bool
    {
        return $token->type === TokenType::OPERATOR && $token->value === 'NOT';
    }

    /**
     * Determines if a token is an EXISTS operator (*).
     *
     * @param  TokenRecord  $token  The token to check
     * @return bool True if the token is an EXISTS operator
     */
    private function isExistsOperator(TokenRecord $token): bool
    {
        return $token->type === TokenType::OPERATOR && $token->value === '*';
    }

    /**
     * Determines if a token is a NOT_EXISTS operator (#).
     *
     * @param  TokenRecord  $token  The token to check
     * @return bool True if the token is a NOT_EXISTS operator
     */
    private function isNotExistsOperator(TokenRecord $token): bool
    {
        return $token->type === TokenType::OPERATOR && $token->value === '#';
    }

    /**
     * Determines if a token is an opening parenthesis.
     *
     * @param  TokenRecord  $token  The token to check
     * @return bool True if the token is an opening parenthesis
     */
    private function isOpeningParenthesis(TokenRecord $token): bool
    {
        return $token->type === TokenType::PAREN && $token->value === '(';
    }
}
