<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Contracts\Filters;

interface AggregateFunctionInterface
{
    /**
     * Exécute la fonction avec des arguments multiples
     *
     * @param  array<string, mixed>  $data  Les données complètes du cluster
     * @param  array<string, mixed>  $args  Les arguments de la fonction
     * @return mixed Résultat de la fonction
     */
    public function execute(array $data, array $args): mixed;

    /**
     * Nom de la fonction
     */
    public function getName(): string;

    /**
     * Valeur par défaut si la fonction échoue
     */
    public function getDefaultValue(): mixed;

    /**
     * Type de retour (int, float, string, bool, mixed)
     */
    public function getReturnType(): string;

    /**
     * Indique si la fonction retourne directement un booléen
     * Si true, pas besoin d'opérateur de comparaison
     */
    public function returnsBoolean(): bool;

    /**
     * Nombre minimum d'arguments requis
     */
    public function getMinArgs(): int;

    /**
     * Nombre maximum d'arguments (0 = illimité)
     */
    public function getMaxArgs(): int;

    /**
     * Valide les arguments
     */
    public function validateArgs(array $args): bool;
}
