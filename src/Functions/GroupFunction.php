<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * GROUP function - groups expressions for complex logical combinations.
 *
 * This function allows grouping of complex expressions to control evaluation order.
 * L'évaluation est déléguée au service qui appelle la fonction.
 *
 * @example
 * // Group expressions with AND/OR
 * {GROUP({COUNT(addresses) > 1} & {AVG(scores) >= 85})} | {HAS(tags, "php")}
 * @example
 * // Nested groups
 * {GROUP({GROUP({COUNT(addresses) > 1} & {AVG(scores) >= 85})} | {HAS(tags, "php")})}
 */
final class GroupFunction extends AbstractAggregateFunction
{
    /**
     * Gets the function name.
     *
     * @return string The function name 'GROUP'
     */
    public function getName(): string
    {
        return 'GROUP';
    }

    /**
     * Executes the GROUP function.
     * Retourne l'expression elle-même pour que le service appelant l'évalue.
     *
     * @param  array<string, mixed>  $data  The data to evaluate against
     * @param  array<int, string>  $args  The function arguments
     * @return mixed The expression to evaluate or false if empty
     */
    public function execute(array $data, array $args): mixed
    {
        if (empty($args)) {
            return false;
        }

        if (is_array($args[0]) && isset($args[0]['original'])) {
            return $args[0]['original'];
        }

        return $args[0];
    }

    /**
     * Gets the minimum number of arguments required.
     *
     * @return int Minimum 1 argument
     */
    public function getMinArgs(): int
    {
        return 1;
    }

    /**
     * Gets the maximum number of arguments allowed.
     *
     * @return int Maximum 1 argument
     */
    public function getMaxArgs(): int
    {
        return 1;
    }

    /**
     * Validates the function arguments.
     *
     * @param  array<mixed>  $args  The arguments to validate
     * @return bool True if arguments are valid
     */
    public function validateArgs(array $args): bool
    {
        return count($args) === 1 && ! empty($args[0]);
    }

    /**
     * Gets the default value when function fails.
     *
     * @return bool Default value false
     */
    public function getDefaultValue(): mixed
    {
        return false;
    }

    /**
     * Gets the return type of the function.
     *
     * @return string The return type 'bool'
     */
    public function getReturnType(): string
    {
        return 'bool';
    }

    /**
     * Determines if this function returns a boolean value.
     *
     * @return bool True if returns boolean
     */
    public function returnsBoolean(): bool
    {
        return true;
    }
}
