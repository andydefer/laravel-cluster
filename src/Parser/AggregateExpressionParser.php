<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Parser;

use AndyDefer\LaravelCluster\Enums\AggregateOperator;
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;
use InvalidArgumentException;

/**
 * Parses aggregate function expressions with support for nested functions
 * and logical combinations.
 *
 * This parser handles expressions like:
 * - `{COUNT(addresses) > 2}`
 * - `{COUNT({LENGTH(name) > 5}) > 2}`
 * - `{COUNT({LENGTH(name) > 5} & {SUM(prices) > 100}) > 2}`
 *
 * @example
 * $parser = new AggregateExpressionParser($registry);
 * $result = $parser->parse('{COUNT(addresses) > 2}');
 * // Returns: ['functionName' => 'COUNT', 'args' => ['addresses'], 'operator' => '>', 'value' => 2]
 */
final class AggregateExpressionParser
{
    private AggregateFunctionRegistry $registry;

    private array $parsedCache = [];

    public function __construct(AggregateFunctionRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Parses an aggregate expression and returns its structured representation.
     *
     * @param  string  $expression  The expression to parse (e.g., `{COUNT(addresses) > 2}`)
     * @return array{functionName: string, args: array, operator: AggregateOperator|null, value: mixed}|null
     *                                                                                                       The parsed structure or null if parsing fails
     */
    public function parse(string $expression): ?array
    {
        $cacheKey = md5($expression);

        if (isset($this->parsedCache[$cacheKey])) {
            return $this->parsedCache[$cacheKey];
        }

        $result = $this->parseRecursive($expression);

        if ($result !== null) {
            $this->parsedCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Splits a compound expression into individual parts with their operators.
     *
     * @param  string  $expression  The compound expression (e.g., `{A} & {B} | {C}`)
     * @return array<int, array{expression: string, operator: string}>
     *                                                                 Array of parts with their associated operators
     */
    public function split(string $expression): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $insideFunction = false;
        $i = 0;
        $length = strlen($expression);
        $operators = [];
        $operatorIndex = 0;

        while ($i < $length) {
            $char = $expression[$i];

            if ($char === '{') {
                $depth++;
                $insideFunction = true;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === '}') {
                $depth--;
                $current .= $char;
                $i++;

                if ($depth === 0) {
                    $insideFunction = false;
                    $operator = $operatorIndex < count($operators) ? $operators[$operatorIndex] : '&';
                    $operatorIndex++;

                    $parts[] = [
                        'expression' => trim($current),
                        'operator' => $operator,
                    ];
                    $current = '';
                }

                continue;
            }

            if (! $insideFunction && $depth === 0 && ($char === '&' || $char === '|')) {
                $operators[] = $char;
                $i++;

                continue;
            }

            if ($insideFunction || $depth > 0) {
                $current .= $char;
            }

            $i++;
        }

        if (trim($current) !== '') {
            $operator = $operatorIndex < count($operators) ? $operators[$operatorIndex] : '&';
            $parts[] = [
                'expression' => trim($current),
                'operator' => $operator,
            ];
        }

        $this->propagateOperators($parts, $operators);

        return $parts;
    }

    /**
     * Recursively parses an expression, handling nested functions and complex expressions.
     *
     * @param  string  $expression  The expression to parse
     * @return array{functionName: string, args: array, operator: AggregateOperator|null, value: mixed}|null
     */
    private function parseRecursive(string $expression): ?array
    {
        if (! preg_match('/\{([A-Z_]+)\(/', $expression, $nameMatch)) {
            return null;
        }

        $functionName = strtoupper($nameMatch[1]);

        if (! $this->registry->has($functionName)) {
            return null;
        }

        $openPos = strpos($expression, '(');
        if ($openPos === false) {
            return null;
        }

        $closePos = $this->findMatchingParen($expression, $openPos);
        if ($closePos === null) {
            return null;
        }

        $argsString = substr($expression, $openPos + 1, $closePos - $openPos - 1);
        $args = $this->parseArgsWithNested($argsString);

        $function = $this->registry->get($functionName);

        if (! $function->validateArgs($args)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid arguments for function "%s". Required: %d-%d args, got %d',
                    $functionName,
                    $function->getMinArgs(),
                    $function->getMaxArgs() ?: 'unlimited',
                    count($args)
                )
            );
        }

        $remaining = trim(substr($expression, $closePos + 1));
        $operator = null;
        $value = null;

        if ($remaining !== '' && ! $function->returnsBoolean()) {
            if (preg_match('/^([><=!]+)\s*(.+)$/', $remaining, $opMatch)) {
                $operator = AggregateOperator::fromValue(trim($opMatch[1]));
                if ($operator !== null) {
                    // ✅ Nettoyer la valeur : supprimer les accolades et les guillemets
                    $rawValue = trim($opMatch[2]);
                    $rawValue = trim($rawValue, ' {}');  // Supprime les espaces, { et }
                    $rawValue = trim($rawValue, '"\'');
                    $value = $this->castValue($functionName, $rawValue);
                }
            }
        }

        return [
            'functionName' => $functionName,
            'args' => $args,
            'operator' => $operator,
            'value' => $value,
        ];
    }

    /**
     * Parses arguments with support for nested functions and complex expressions.
     *
     * @param  string  $argsString  The raw arguments string
     * @return array<int, mixed> Array of parsed arguments
     */
    private function parseArgsWithNested(string $argsString): array
    {
        if (trim($argsString) === '') {
            return [];
        }

        $args = [];
        $current = '';
        $inQuotes = false;
        $inBrackets = 0;
        $inFunction = 0;
        $i = 0;
        $length = strlen($argsString);

        while ($i < $length) {
            $char = $argsString[$i];

            if ($char === '"' || $char === "'") {
                $inQuotes = ! $inQuotes;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === '{') {
                $inFunction++;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === '}') {
                $inFunction--;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === '[') {
                $inBrackets++;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === ']') {
                $inBrackets--;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === ',' && ! $inQuotes && $inBrackets === 0 && $inFunction === 0) {
                $arg = trim($current);
                if ($arg !== '') {
                    $args[] = $this->normalizeArgWithNested($arg);
                }
                $current = '';
                $i++;

                continue;
            }

            $current .= $char;
            $i++;
        }

        if (trim($current) !== '') {
            $arg = trim($current);
            if ($arg !== '') {
                $args[] = $this->normalizeArgWithNested($arg);
            }
        }

        return $args;
    }

    /**
     * Normalizes a single argument with support for nested functions and complex expressions.
     *
     * @param  string  $arg  The raw argument string
     * @return mixed Normalized argument value
     */
    private function normalizeArgWithNested(string $arg): mixed
    {
        $arg = trim($arg);

        if (preg_match('/\s+(&|\|)\s+/', $arg)) {
            $parts = $this->split($arg);
            $parsedParts = [];

            foreach ($parts as $part) {
                $expression = trim($part['expression']);
                if (str_starts_with($expression, '{') && str_ends_with($expression, '}')) {
                    $parsed = $this->parseRecursive($expression);
                    $parsedParts[] = [
                        'expression' => $expression,
                        'operator' => $part['operator'],
                        'parsed' => $parsed,
                    ];
                } else {
                    $parsedParts[] = [
                        'expression' => $expression,
                        'operator' => $part['operator'],
                        'parsed' => null,
                    ];
                }
            }

            return [
                'type' => 'complex_expression',
                'parts' => $parsedParts,
                'original' => $arg,
            ];
        }

        if (str_starts_with($arg, '{') && str_ends_with($arg, '}')) {
            $parsed = $this->parseRecursive($arg);

            return $parsed ?? $arg;
        }

        return $this->normalizeArg($arg);
    }

    /**
     * Builds a result structure from regex matches.
     *
     * @param  array<int, string>  $matches  Regex match groups
     * @return array{functionName: string, args: array, operator: AggregateOperator|null, value: mixed}|null
     */
    private function buildResult(array $matches): ?array
    {
        $functionName = strtoupper($matches[1]);

        if (! $this->registry->has($functionName)) {
            return null;
        }

        $argsString = trim($matches[2]);
        $args = $this->parseArgs($argsString);

        $function = $this->registry->get($functionName);

        if (! $function->validateArgs($args)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid arguments for function "%s". Required: %d-%d args, got %d',
                    $functionName,
                    $function->getMinArgs(),
                    $function->getMaxArgs() ?: 'unlimited',
                    count($args)
                )
            );
        }

        $operator = null;
        $value = null;
        $returnsBoolean = $function->returnsBoolean();

        if (isset($matches[3]) && isset($matches[4]) && ! $returnsBoolean) {
            $operator = AggregateOperator::fromValue(trim($matches[3]));

            if ($operator === null) {
                return null;
            }

            // ✅ Nettoyer la valeur
            $rawValue = trim($matches[4]);
            $rawValue = trim($rawValue, ' {}');
            $rawValue = trim($rawValue, '"\'');
            $value = $this->castValue($functionName, $rawValue);
        }

        return [
            'functionName' => $functionName,
            'args' => $args,
            'operator' => $operator,
            'value' => $value,
        ];
    }

    /**
     * Legacy manual parser for expressions that don't match the standard pattern.
     *
     * @param  string  $expression  The expression to parse
     * @return array{functionName: string, args: array, operator: AggregateOperator|null, value: mixed}|null
     */
    private function parseManually(string $expression): ?array
    {
        if (! preg_match('/\{([A-Z_]+)\(/', $expression, $nameMatch)) {
            return null;
        }

        $functionName = strtoupper($nameMatch[1]);

        if (! $this->registry->has($functionName)) {
            return null;
        }

        $openPos = strpos($expression, '(');
        if ($openPos === false) {
            return null;
        }

        $closePos = $this->findMatchingParen($expression, $openPos);
        if ($closePos === null) {
            return null;
        }

        $argsString = substr($expression, $openPos + 1, $closePos - $openPos - 1);
        $args = $this->parseArgs($argsString);

        $function = $this->registry->get($functionName);

        if (! $function->validateArgs($args)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid arguments for function "%s". Required: %d-%d args, got %d',
                    $functionName,
                    $function->getMinArgs(),
                    $function->getMaxArgs() ?: 'unlimited',
                    count($args)
                )
            );
        }

        $remaining = trim(substr($expression, $closePos + 1));
        $operator = null;
        $value = null;

        if ($remaining !== '' && ! $function->returnsBoolean()) {
            if (preg_match('/^([><=!]+)\s*(.+)$/', $remaining, $opMatch)) {
                $operator = AggregateOperator::fromValue(trim($opMatch[1]));
                if ($operator !== null) {
                    // ✅ Nettoyer la valeur
                    $rawValue = trim($opMatch[2]);
                    $rawValue = trim($rawValue, ' {}');
                    $rawValue = trim($rawValue, '"\'');
                    $value = $this->castValue($functionName, $rawValue);
                }
            }
        }

        return [
            'functionName' => $functionName,
            'args' => $args,
            'operator' => $operator,
            'value' => $value,
        ];
    }

    /**
     * Finds the position of the closing parenthesis that matches the opening one.
     *
     * @param  string  $string  The string to search in
     * @param  int  $openPos  The position of the opening parenthesis
     * @return int|null The position of the closing parenthesis or null if not found
     */
    private function findMatchingParen(string $string, int $openPos): ?int
    {
        $depth = 0;
        $length = strlen($string);

        for ($i = $openPos; $i < $length; $i++) {
            if ($string[$i] === '(') {
                $depth++;
            } elseif ($string[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Parses a string of arguments into an array of normalized values.
     *
     * @param  string  $argsString  The raw arguments string
     * @return array<int, mixed> Array of parsed arguments
     */
    private function parseArgs(string $argsString): array
    {
        if (trim($argsString) === '') {
            return [];
        }

        $args = [];
        $current = '';
        $inQuotes = false;
        $inBrackets = 0;
        $inFunction = 0;
        $i = 0;
        $length = strlen($argsString);

        while ($i < $length) {
            $char = $argsString[$i];

            if ($char === '"' || $char === "'") {
                $inQuotes = ! $inQuotes;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === '{') {
                $inFunction++;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === '}') {
                $inFunction--;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === '[') {
                $inBrackets++;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === ']') {
                $inBrackets--;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === ',' && ! $inQuotes && $inBrackets === 0 && $inFunction === 0) {
                $args[] = $this->normalizeArg(trim($current));
                $current = '';
                $i++;

                continue;
            }

            $current .= $char;
            $i++;
        }

        if (trim($current) !== '') {
            $args[] = $this->normalizeArg(trim($current));
        }

        return $args;
    }

    /**
     * Normalizes a single argument value.
     *
     * @param  string  $arg  The raw argument string
     * @return mixed Normalized value (string, int, float, bool, array, or null)
     */
    private function normalizeArg(string $arg): mixed
    {
        $arg = trim($arg);

        if (str_starts_with($arg, '$')) {
            return ['type' => 'variable', 'value' => substr($arg, 1)];
        }

        if (str_starts_with($arg, '[') && str_ends_with($arg, ']')) {
            $inner = substr($arg, 1, -1);
            if (trim($inner) === '') {
                return [];
            }

            return $this->parseArrayContent($inner);
        }

        if ((str_starts_with($arg, '"') && str_ends_with($arg, '"')) ||
            (str_starts_with($arg, "'") && str_ends_with($arg, "'"))) {
            $content = substr($arg, 1, -1);
            $content = str_replace('\\"', '"', $content);
            $content = str_replace("\\'", "'", $content);

            return $content;
        }

        if ($arg === 'true') {
            return true;
        }
        if ($arg === 'false') {
            return false;
        }
        if ($arg === 'null') {
            return null;
        }

        if (is_numeric($arg)) {
            return str_contains($arg, '.') ? (float) $arg : (int) $arg;
        }

        return $arg;
    }

    /**
     * Parses an array string into a PHP array.
     *
     * @param  string  $content  The array content (without brackets)
     * @return array<int, mixed> The parsed array
     */
    private function parseArrayContent(string $content): array
    {
        $items = [];
        $current = '';
        $inQuotes = false;
        $inBrackets = 0;
        $i = 0;
        $length = strlen($content);

        while ($i < $length) {
            $char = $content[$i];

            if ($char === '"' || $char === "'") {
                $inQuotes = ! $inQuotes;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === '[') {
                $inBrackets++;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === ']') {
                $inBrackets--;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === ',' && ! $inQuotes && $inBrackets === 0) {
                $items[] = $this->normalizeArg(trim($current));
                $current = '';
                $i++;

                continue;
            }

            $current .= $char;
            $i++;
        }

        if (trim($current) !== '') {
            $items[] = $this->normalizeArg(trim($current));
        }

        return $items;
    }

    /**
     * Casts a value according to the function's expected return type.
     *
     * @param  string  $functionName  The function name
     * @param  string  $value  The raw value to cast
     * @return mixed The casted value
     */
    private function castValue(string $functionName, string $value): mixed
    {
        $function = $this->registry->get($functionName);
        $returnType = $function?->getReturnType() ?? 'string';

        return match ($returnType) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }

    /**
     * Propagates operators across all parts of a split expression.
     *
     * @param  array<int, array{expression: string, operator: string}>  $parts  The split parts
     * @param  array<int, string>  $operators  The detected operators
     */
    private function propagateOperators(array &$parts, array $operators): void
    {
        if (count($parts) > 1 && count($operators) === 1) {
            $singleOperator = $operators[0];
            foreach ($parts as $index => $part) {
                $parts[$index]['operator'] = $singleOperator;
            }
        } elseif (count($parts) > 1 && count($operators) > 1) {
            $parts[0]['operator'] = $operators[0];
            for ($i = 1; $i < count($parts) && $i < count($operators); $i++) {
                $parts[$i]['operator'] = $operators[$i];
            }
            for ($i = count($operators); $i < count($parts); $i++) {
                $parts[$i]['operator'] = end($operators);
            }
        }
    }
}
