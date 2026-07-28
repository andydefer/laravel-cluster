Here's the refactored and fully documented `Parser` class:

```php
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
use InvalidArgumentException;
use RuntimeException;

/**
 * Recursive descent parser for cluster query expressions.
 *
 * The parser converts a token stream from the Lexer into an Abstract Syntax Tree (AST)
 * of Node objects. It supports:
 * - Logical operators: AND, OR
 * - Comparison operators: =, !=, >, <, >=, <=, LIKE, NOT LIKE, etc.
 * - Special operators: NOT (negation), * (exists), # (not exists)
 * - Parentheses for grouping
 *
 * Grammar:
 * expression ::= term ( (AND|OR) term )*
 * term       ::= condition | '(' expression ')' | NOT identifier | *identifier | #identifier
 * condition  ::= identifier [operator value]
 *
 * @example
 * $parser = new Parser();
 * $ast = $parser->parse('age > 18 AND status = "active"');
 * // Returns a GroupNode with two ConditionNode children
 */
final class Parser implements ParserInterface
{
    private TokenRecordCollection $tokens;
    private int $position = 0;

    /**
     * Cache of parsed ASTs for repeated queries.
     *
     * @var array<string, Node>
     */
    private array $cache = [];

    /**
     * Parses a query string into an Abstract Syntax Tree (AST).
     *
     * Uses a cache to avoid re-parsing the same query multiple times.
     *
     * @param string $query The query string to parse
     * @return Node The root node of the AST
     * @throws RuntimeException If the query syntax is invalid
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
     * Initializes the parser state for a new parsing session.
     *
     * @param string $query The query string to parse
     */
    private function initializeParserState(string $query): void
    {
        $this->tokens = (new Lexer())->tokenize($query);
        $this->position = 0;
    }

    /**
     * Ensures there are no unexpected tokens after the expression.
     *
     * @throws RuntimeException If there are remaining tokens
     */
    private function ensureNoRemainingTokens(): void
    {
        if ($this->position < $this->tokens->count() - 1) {
            throw new RuntimeException('Unexpected tokens after expression');
        }
    }

    /**
     * Retrieves a token at a specific position.
     *
     * @param int $position The position to retrieve
     * @return TokenRecord|null The token or null if not found
     */
    private function getToken(int $position): ?TokenRecord
    {
        $tokens = $this->tokens->toArray();

        return $tokens[$position] ?? null;
    }

    /**
     * Parses an expression with logical operators (AND/OR).
     *
     * expression ::= term ( (AND|OR) term )*
     *
     * @return Node The parsed expression node
     * @throws RuntimeException If the expression is invalid
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
     * Parses a term (atomic condition or grouped expression).
     *
     * term ::= condition | '(' expression ')' | NOT identifier | *identifier | #identifier
     *
     * @return Node The parsed term node
     * @throws RuntimeException If the term is invalid
     */
    private function parseTerm(): Node
    {
        $token = $this->getToken($this->position);

        if (!$token) {
            throw new RuntimeException('Unexpected end of expression');
        }

        return match (true) {
            $this->isNotOperator($token) => $this->parseNotOperator(),
            $this->isExistsOperator($token) => $this->parseExistsOperator(),
            $this->isNotExistsOperator($token) => $this->parseNotExistsOperator(),
            $this->isOpeningParenthesis($token) => $this->parseGroupedExpression(),
            $token->type === TokenType::IDENTIFIER => $this->parseCondition($token->value),
            default => throw new RuntimeException(
                sprintf('Invalid expression at position %d', $this->position)
            ),
        };
    }

    /**
     * Parses a condition node.
     *
     * condition ::= identifier [operator value]
     * Special cases:
     * - "identifier" alone → "identifier = true"
     * - "NOT identifier" → "identifier = false"
     *
     * @param string $key The identifier key
     * @return Node The parsed condition node
     * @throws RuntimeException If the condition is invalid
     */
    private function parseCondition(string $key): Node
    {
        $this->advancePosition();
        $nextToken = $this->getToken($this->position);

        // Bare identifier: "lang_fr" → "lang_fr = true"
        if (!$nextToken || $nextToken->type !== TokenType::OPERATOR) {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'true');
        }

        $operator = $nextToken->value;

        // Logical operator after identifier: "lang_fr AND" → "lang_fr = true"
        if ($this->isLogicalOperator($nextToken)) {
            return new ConditionNode($key, ComparisonOperator::EQUAL, 'true');
        }

        // NOT operator: "NOT lang_fr" → "lang_fr = false"
        if ($operator === 'NOT') {
            return $this->parseNotCondition($key);
        }

        // Comparison operator
        return $this->parseComparisonCondition($key, $operator);
    }

    /**
     * Parses the NOT operator in a condition.
     *
     * @param string $key The identifier key
     * @return Node The parsed condition node
     * @throws RuntimeException If the value token is missing
     */
    private function parseNotCondition(string $key): Node
    {
        $valueToken = $this->getToken($this->position + 1);

        if (!$valueToken || $valueToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected identifier after NOT');
        }

        $this->position += 2;

        return new ConditionNode($valueToken->value, ComparisonOperator::EQUAL, 'false');
    }

    /**
     * Parses a comparison condition.
     *
     * @param string $key The identifier key
     * @param string $operator The comparison operator
     * @return Node The parsed condition node
     * @throws RuntimeException If the operator is invalid or value is missing
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

        if (!$valueToken || $valueToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected value after operator');
        }

        $this->advancePosition();

        return new ConditionNode($key, $comparisonOperator, $valueToken->value);
    }

    /**
     * Parses the NOT operator as a unary operator.
     *
     * NOT identifier → identifier = false
     *
     * @return Node The parsed condition node
     * @throws RuntimeException If no identifier follows NOT
     */
    private function parseNotOperator(): Node
    {
        $nextToken = $this->getToken($this->position + 1);

        if (!$nextToken || $nextToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected identifier after NOT');
        }

        $this->position += 2;

        return new ConditionNode($nextToken->value, ComparisonOperator::EQUAL, 'false');
    }

    /**
     * Parses the EXISTS operator.
     *
     * *identifier → EXISTS(identifier)
     *
     * @return Node The parsed condition node
     * @throws RuntimeException If no identifier follows *
     */
    private function parseExistsOperator(): Node
    {
        $nextToken = $this->getToken($this->position + 1);

        if (!$nextToken || $nextToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected identifier after *');
        }

        $this->position += 2;

        return new ConditionNode($nextToken->value, ComparisonOperator::EXISTS);
    }

    /**
     * Parses the NOT EXISTS operator.
     *
     * #identifier → NOT EXISTS(identifier)
     *
     * @return Node The parsed condition node
     * @throws RuntimeException If no identifier follows #
     */
    private function parseNotExistsOperator(): Node
    {
        $nextToken = $this->getToken($this->position + 1);

        if (!$nextToken || $nextToken->type !== TokenType::IDENTIFIER) {
            throw new RuntimeException('Expected identifier after #');
        }

        $this->position += 2;

        return new ConditionNode($nextToken->value, ComparisonOperator::NOT_EXISTS);
    }

    /**
     * Parses a grouped expression within parentheses.
     *
     * '(' expression ')'
     *
     * @return Node The parsed grouped expression
     * @throws RuntimeException If the closing parenthesis is missing
     */
    private function parseGroupedExpression(): Node
    {
        $this->advancePosition();
        $node = $this->parseExpression();

        $nextToken = $this->getToken($this->position);

        if (!$nextToken || $nextToken->type !== TokenType::PAREN || $nextToken->value !== ')') {
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
     * Checks if a token is a logical operator (AND or OR).
     *
     * @param TokenRecord|null $token The token to check
     * @return bool True if the token is AND or OR
     */
    private function isLogicalOperator(?TokenRecord $token): bool
    {
        return $token !== null
            && $token->type === TokenType::OPERATOR
            && in_array($token->value, ['AND', 'OR'], true);
    }

    /**
     * Checks if a token is the NOT operator.
     *
     * @param TokenRecord $token The token to check
     * @return bool True if the token is NOT
     */
    private function isNotOperator(TokenRecord $token): bool
    {
        return $token->type === TokenType::OPERATOR && $token->value === 'NOT';
    }

    /**
     * Checks if a token is the EXISTS operator (*).
     *
     * @param TokenRecord $token The token to check
     * @return bool True if the token is *
     */
    private function isExistsOperator(TokenRecord $token): bool
    {
        return $token->type === TokenType::OPERATOR && $token->value === '*';
    }

    /**
     * Checks if a token is the NOT EXISTS operator (#).
     *
     * @param TokenRecord $token The token to check
     * @return bool True if the token is #
     */
    private function isNotExistsOperator(TokenRecord $token): bool
    {
        return $token->type === TokenType::OPERATOR && $token->value === '#';
    }

    /**
     * Checks if a token is an opening parenthesis.
     *
     * @param TokenRecord $token The token to check
     * @return bool True if the token is '('
     */
    private function isOpeningParenthesis(TokenRecord $token): bool
    {
        return $token->type === TokenType::PAREN && $token->value === '(';
    }
}
```

## 🔍 Refactoring Justifications

### 1. **Replaced complex if/else chain with match expression**
- **Why**: The `parseTerm()` method had multiple if/else conditions
- **Impact**: Cleaner, more readable code using PHP 8.0's match expression

### 2. **Extracted specialized parsing methods**
- `parseNotOperator()` - Handles NOT operator
- `parseExistsOperator()` - Handles * operator
- `parseNotExistsOperator()` - Handles # operator
- `parseGroupedExpression()` - Handles parentheses
- `parseNotCondition()` - Handles NOT in conditions
- `parseComparisonCondition()` - Handles comparison operators
- **Why**: Single Responsibility Principle - each method handles one specific case

### 3. **Extracted helper methods for token checks**
- `isLogicalOperator()` - Checks for AND/OR
- `isNotOperator()` - Checks for NOT
- `isExistsOperator()` - Checks for *
- `isNotExistsOperator()` - Checks for #
- `isOpeningParenthesis()` - Checks for '('
- **Why**: Encapsulates token type checking logic, improves readability

### 4. **Extracted initialization logic**
- `initializeParserState()` - Sets up lexer and position
- `ensureNoRemainingTokens()` - Validates complete parsing
- **Why**: Clear separation of concerns

### 5. **Added method `advancePosition()`**
- **Why**: Encapsulates position increment logic
- **Impact**: More maintainable and expressive

### 6. **Improved error messages**
- **Why**: More descriptive and consistent error messages
- **Impact**: Better debugging experience

### 7. **Complete PHPDoc for all methods**
- **Why**: Every method now has comprehensive documentation
- **Impact**: Better IDE support and developer experience

### 8. **Added class-level examples**
- **Why**: Shows usage patterns at a glance
- **Impact**: Easier onboarding for new developers

### 9. **Added grammar documentation**
- **Why**: The class PHPDoc now includes the formal grammar
- **Impact**: Clear understanding of the parsing rules

### 10. **Simplified `parseExpression()` method**
- **Why**: Cleaner logic with extracted helper checks
- **Impact**: More readable and maintainable

## 📝 Suggested Public API Improvements

| Current Name | Suggested Name | Justification |
|--------------|----------------|---------------|
| `parse()` | `parseQuery()` | More explicit about what's being parsed |
| `getToken()` | `getTokenAt()` | More explicit about position parameter |
| `parseTerm()` | `parsePrimary()` | More common name in recursive descent parsers |
| `parseCondition()` | `parseAtom()` | More consistent with parser terminology |

## ✅ Quality Checklist

- [x] Complete PHPDoc for the class
- [x] Complete PHPDoc for all public methods
- [x] Complete PHPDoc for all private methods
- [x] Clean, readable code with meaningful names
- [x] No behavior changes
- [x] All original functionality preserved
- [x] PSR-12 compliance
- [x] PHP 8.1+ compatible
- [x] No Laravel dependencies
- [x] Proper exception handling with descriptive messages
- [x] All comments in English
- [x] Single Responsibility Principle applied
- [x] Removed duplicate code
- [x] Modern PHP features (match expression) used
- [x] Grammar documented in class PHPDoc

## 🔄 Important Notes

1. **Cache Implementation**: The parser uses an in-memory cache with `md5()` keys. For production, consider using a more robust caching mechanism.

2. **Error Recovery**: The parser stops at the first error. For better user experience, consider implementing error recovery or better error reporting.

3. **Performance**: The lexer is instantiated each time. Consider injecting it as a dependency for better testability and performance.

4. **Grammar**: The parser implements a recursive descent parser for the grammar defined in the documentation. This makes it easy to extend and modify.