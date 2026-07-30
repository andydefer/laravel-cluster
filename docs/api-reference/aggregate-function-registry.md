# AggregateFunctionRegistry - Technical Reference

## Description

Le registre des fonctions d'agrégation gère l'ensemble des fonctions disponibles pour les expressions de requête sur les données en mémoire. Il assure l'enregistrement, la résolution et l'exécution des fonctions d'agrégation comme COUNT, SUM, AVG, MIN, MAX, HAS, ALL, etc.

## Hiérarchie

```
AggregateFunctionRegistry
    └── Implémente le pattern Registry
```

## Rôle principal

Centralise l'accès aux fonctions d'agrégation et fournit une interface unifiée pour :
- L'exécution des fonctions sur des données
- La validation des arguments
- La découverte des types de retour
- La classification des fonctions (booléennes vs numériques)

---

## API

### `__construct()`

Initialise le registre et enregistre les fonctions par défaut.

**Fonctions par défaut :**
- `CountFunction` - Compte les éléments
- `SumFunction` - Somme des valeurs
- `AvgFunction` - Moyenne des valeurs
- `MinFunction` - Valeur minimale
- `MaxFunction` - Valeur maximale
- `LengthFunction` - Longueur d'une chaîne ou d'un tableau
- `ExistsFunction` - Vérifie l'existence d'une valeur non vide
- `HasFunction` - Recherche une valeur ou une paire clé-valeur
- `AllFunction` - Vérifie que tous les éléments satisfont une condition
- `IsEmptyFunction` - Vérifie si une valeur est vide

---

### `register(AggregateFunctionInterface $function): self`

Enregistre une fonction d'agrégation dans le registre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$function` | `AggregateFunctionInterface` | La fonction à enregistrer |

**Retourne :** `self` - L'instance du registre pour le chaînage

**Exemple :**
```php
$registry = new AggregateFunctionRegistry();
$registry->register(new CustomFunction());
```

---

### `has(string $name): bool`

Vérifie si une fonction est enregistrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction (insensible à la casse) |

**Retourne :** `bool` - `true` si la fonction est enregistrée

**Exemple :**
```php
$registry->has('COUNT'); // true
$registry->has('UNKNOWN'); // false
```

---

### `get(string $name): ?AggregateFunctionInterface`

Récupère une fonction enregistrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction (insensible à la casse) |

**Retourne :** `AggregateFunctionInterface|null` - L'instance de la fonction ou `null`

**Exemple :**
```php
$function = $registry->get('COUNT');
if ($function) {
    $result = $function->execute($data, ['addresses']);
}
```

---

### `execute(string $name, array $data, array $args): mixed`

Exécute une fonction enregistrée avec les données et arguments fournis.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction à exécuter |
| `$data` | `array<string, mixed>` | Les données à traiter |
| `$args` | `array<int, string>` | Les arguments de la fonction |

**Retourne :** `mixed` - Le résultat de l'exécution de la fonction

**Exceptions :** `InvalidArgumentException` - Si la fonction n'est pas enregistrée

**Exemple :**
```php
$data = ['addresses' => ['a', 'b', 'c']];
$result = $registry->execute('COUNT', $data, ['addresses']); // 3
```

---

### `getDefaultValue(string $name): mixed`

Retourne la valeur par défaut pour une fonction.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `mixed` - La valeur par défaut, ou `0` si la fonction n'est pas trouvée

**Exemple :**
```php
$default = $registry->getDefaultValue('COUNT'); // 0
$default = $registry->getDefaultValue('AVG'); // 0.0
$default = $registry->getDefaultValue('EXISTS'); // false
```

---

### `all(): array`

Retourne toutes les fonctions enregistrées.

**Retourne :** `array<string, AggregateFunctionInterface>` - Tableau des fonctions indexées par nom

---

### `getBooleanFunctions(): array`

Retourne les fonctions qui retournent des résultats booléens.

**Retourne :** `array<string, AggregateFunctionInterface>` - Tableau des fonctions booléennes

**Exemple :**
```php
$booleanFunctions = $registry->getBooleanFunctions();
// ['EXISTS' => ExistsFunction, 'HAS' => HasFunction, ...]
```

---

### `getNumericFunctions(): array`

Retourne les fonctions qui retournent des résultats numériques.

**Retourne :** `array<string, AggregateFunctionInterface>` - Tableau des fonctions numériques

**Exemple :**
```php
$numericFunctions = $registry->getNumericFunctions();
// ['COUNT' => CountFunction, 'SUM' => SumFunction, ...]
```

---

## Cas d'utilisation

### Cas 1 : Exécution d'une fonction sur des données

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;

$registry = new AggregateFunctionRegistry();

$data = [
    'addresses' => ['a', 'b', 'c'],
    'scores' => [80, 90, 100],
    'prices' => [100, 200, 300],
];

// Comptage
$count = $registry->execute('COUNT', $data, ['addresses']); // 3

// Somme
$sum = $registry->execute('SUM', $data, ['prices']); // 600.0

// Moyenne
$avg = $registry->execute('AVG', $data, ['scores']); // 90.0

// Minimum
$min = $registry->execute('MIN', $data, ['scores']); // 80.0

// Maximum
$max = $registry->execute('MAX', $data, ['scores']); // 100.0
```

### Cas 2 : Fonctions booléennes

```php
// Vérification d'existence
$exists = $registry->execute('EXISTS', $data, ['addresses']);
// true (addresses existe et n'est pas vide)

// Recherche d'une valeur
$has = $registry->execute('HAS', $data, ['tags', 'php']);
// Vérifie si 'php' est dans le tableau 'tags'

// Vérification que tous les éléments satisfont une condition
$all = $registry->execute('ALL', $data, ['items', 'status', 'active']);
// Vérifie si tous les items ont status='active'

// Vérification de vacuité
$isEmpty = $registry->execute('IS_EMPTY', $data, ['cart']);
// Vérifie si 'cart' est vide
```

### Cas 3 : Enregistrement d'une fonction personnalisée

```php
use AndyDefer\LaravelCluster\Contracts\AggregateFunctionInterface;

final class CustomFunction extends AbstractAggregateFunction
{
    public function execute(array $data, array $args): mixed
    {
        // Logique personnalisée...
        return $result;
    }
    
    public function getName(): string { return 'CUSTOM'; }
    public function getDefaultValue(): mixed { return 0; }
    public function getReturnType(): string { return 'int'; }
    public function returnsBoolean(): bool { return false; }
    public function getMinArgs(): int { return 1; }
    public function getMaxArgs(): int { return 1; }
    public function validateArgs(array $args): bool { return count($args) === 1; }
}

$registry = new AggregateFunctionRegistry();
$registry->register(new CustomFunction());

$result = $registry->execute('CUSTOM', $data, ['path']);
```

### Cas 4 : Classification des fonctions

```php
// Obtenir toutes les fonctions booléennes
$booleanFunctions = $registry->getBooleanFunctions();
foreach ($booleanFunctions as $name => $function) {
    echo "$name : booléenne\n";
}

// Obtenir toutes les fonctions numériques
$numericFunctions = $registry->getNumericFunctions();
foreach ($numericFunctions as $name => $function) {
    echo "$name : numérique\n";
}
```

---

## Intégration

Le registre est utilisé par :

- **`AggregateEvaluatorService`** : Évaluation des expressions d'agrégation
- **`AggregateExpressionParser`** : Parsing des expressions
- **`ClusterVOCollection`** : Méthodes `whereAggregate()`, `whereAggregateDirect()`, etc.
- **`ClusterServiceProvider`** : Enregistrement dans le conteneur Laravel

---

## Performance

- **Complexité :** O(1) pour l'accès aux fonctions via tableau associatif
- **Mémoire :** Les fonctions sont instanciées une seule fois
- **Cache :** Les résultats d'exécution ne sont pas mis en cache

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;

// Création du registre
$registry = new AggregateFunctionRegistry();

// Vérification des fonctions disponibles
$available = $registry->getNames();
// ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'LENGTH', 'EXISTS', 'HAS', 'ALL', 'IS_EMPTY']

// Vérification d'une fonction
if ($registry->has('COUNT')) {
    $data = ['addresses' => ['a', 'b', 'c']];
    
    // Exécution
    $result = $registry->execute('COUNT', $data, ['addresses']);
    echo "Result: $result\n"; // Result: 3
    
    // Valeur par défaut
    $default = $registry->getDefaultValue('COUNT');
    echo "Default: $default\n"; // Default: 0
}

// Toutes les fonctions
$allFunctions = $registry->all();
foreach ($allFunctions as $name => $function) {
    echo "$name: " . get_class($function) . "\n";
}

// Classification des fonctions
$booleanFunctions = $registry->getBooleanFunctions();
echo "Boolean functions: " . implode(', ', array_keys($booleanFunctions)) . "\n";
// EXISTS, HAS, ALL, IS_EMPTY

$numericFunctions = $registry->getNumericFunctions();
echo "Numeric functions: " . implode(', ', array_keys($numericFunctions)) . "\n";
// COUNT, SUM, AVG, MIN, MAX, LENGTH
```

---

## Voir aussi

- `AggregateFunctionInterface` - Interface des fonctions d'agrégation
- `AbstractAggregateFunction` - Classe abstraite de base
- `AggregateEvaluatorService` - Service d'évaluation
- `AggregateExpressionParser` - Analyseur d'expressions