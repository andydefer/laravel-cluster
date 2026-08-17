# AggregateFunctionRegistry - Référence Technique

## Description

Registre central gérant l'ensemble des fonctions d'agrégation (COUNT, SUM, AVG, MIN, MAX, etc.) utilisables dans les expressions de requête sur les clusters. Ce registre assure l'enregistrement, la résolution et l'exécution des fonctions d'agrégation.

## Hiérarchie

```
AggregateFunctionRegistry
```

## Rôle principal

Le `AggregateFunctionRegistry` est le point d'entrée pour toutes les fonctions d'agrégation. Il :

- Gère le cycle de vie des fonctions (enregistrement, résolution, exécution)
- Fournit des méthodes de filtrage (fonctions booléennes vs numériques)
- Valide les noms de fonctions selon la convention SCREAMING_SNAKE_CASE
- Empêche les enregistrements en double
- Sert de pont entre le parseur d'expressions et les fonctions concrètes

## API / Méthodes publiques

### `register(AggregateFunctionInterface $function): self`

Enregistre une fonction d'agrégation dans le registre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$function` | `AggregateFunctionInterface` | La fonction à enregistrer |

**Retourne :** `self` - L'instance du registre pour le chaînage de méthodes

**Exceptions :**
- `InvalidArgumentException` si une fonction avec le même nom est déjà enregistrée
- `InvalidArgumentException` si le nom de la fonction est invalide (format SCREAMING_SNAKE_CASE)

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;

$registry = new AggregateFunctionRegistry();
$customFunction = new CustomFunction();
$registry->register($customFunction);
```

### `has(string $name): bool`

Vérifie si une fonction est enregistrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction (insensible à la casse) |

**Retourne :** `bool` - `true` si la fonction est enregistrée

**Exemple :**
```php
if ($registry->has('COUNT')) {
    echo "La fonction COUNT est disponible";
}
```

### `get(string $name): ?AggregateFunctionInterface`

Récupère une fonction enregistrée par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction (insensible à la casse) |

**Retourne :** `AggregateFunctionInterface|null` - L'instance de la fonction, ou `null` si non trouvée

**Exemple :**
```php
$countFunction = $registry->get('COUNT');
if ($countFunction !== null) {
    $result = $countFunction->execute($data, ['addresses']);
}
```

### `execute(string $name, array $data, array $args): mixed`

Exécute une fonction enregistrée avec les données et arguments fournis.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction à exécuter |
| `$data` | `array<string, mixed>` | Les données à traiter |
| `$args` | `array<int, string>` | Les arguments de la fonction |

**Retourne :** `mixed` - Le résultat de l'exécution de la fonction

**Exceptions :** `InvalidArgumentException` si la fonction n'est pas enregistrée

**Exemple :**
```php
$data = ['addresses' => ['a', 'b', 'c']];
$result = $registry->execute('COUNT', $data, ['addresses']);
// $result = 3
```

### `getDefaultValue(string $name): mixed`

Retourne la valeur par défaut d'une fonction.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `mixed` - La valeur par défaut, ou `0` si la fonction n'est pas trouvée

**Exemple :**
```php
$default = $registry->getDefaultValue('COUNT'); // 0
$default = $registry->getDefaultValue('EXISTS'); // false
```

### `all(): array`

Retourne toutes les fonctions enregistrées.

**Retourne :** `array<string, AggregateFunctionInterface>` - Tableau des instances de fonctions indexées par leur nom

**Exemple :**
```php
$allFunctions = $registry->all();
foreach ($allFunctions as $name => $function) {
    echo $name . " : " . get_class($function) . "\n";
}
```

### `getBooleanFunctions(): array`

Retourne uniquement les fonctions qui retournent des valeurs booléennes.

**Retourne :** `array<string, AggregateFunctionInterface>` - Tableau des fonctions booléennes

**Exemple :**
```php
$booleanFunctions = $registry->getBooleanFunctions();
// ['EXISTS' => ExistsFunction, 'HAS' => HasFunction, 'ALL' => AllFunction, ...]
```

### `getNumericFunctions(): array`

Retourne uniquement les fonctions qui retournent des valeurs numériques.

**Retourne :** `array<string, AggregateFunctionInterface>` - Tableau des fonctions numériques

**Exemple :**
```php
$numericFunctions = $registry->getNumericFunctions();
// ['COUNT' => CountFunction, 'SUM' => SumFunction, 'AVG' => AvgFunction, ...]
```

### `getNames(): array`

Retourne les noms de toutes les fonctions enregistrées.

**Retourne :** `array<string>` - Tableau des noms de fonctions

**Exemple :**
```php
$names = $registry->getNames();
// ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'LENGTH', 'EXISTS', 'HAS', 'ALL', 'IS_EMPTY', 'MATCHES', 'GROUP']
```

## Cas d'utilisation

### Cas 1 : Enregistrement d'une fonction personnalisée

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;

class MyCustomFunction implements AggregateFunctionInterface
{
    public function getName(): string { return 'MY_FUNCTION'; }
    public function execute(array $data, array $args): mixed { return 'custom result'; }
    public function getMinArgs(): int { return 0; }
    public function getMaxArgs(): int { return PHP_INT_MAX; }
    public function validateArgs(array $args): bool { return true; }
    public function getDefaultValue(): mixed { return null; }
    public function getReturnType(): string { return 'string'; }
    public function returnsBoolean(): bool { return false; }
}

$registry = new AggregateFunctionRegistry();
$registry->register(new MyCustomFunction());

$result = $registry->execute('MY_FUNCTION', [], []);
// $result = 'custom result'
```

### Cas 2 : Filtrage des fonctions par type

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;

$registry = new AggregateFunctionRegistry();

// Récupérer uniquement les fonctions booléennes pour la validation
$booleanFunctions = $registry->getBooleanFunctions();
foreach ($booleanFunctions as $name => $function) {
    echo "Fonction booléenne: $name\n";
}

// Récupérer uniquement les fonctions numériques pour les calculs
$numericFunctions = $registry->getNumericFunctions();
foreach ($numericFunctions as $name => $function) {
    echo "Fonction numérique: $name\n";
}
```

### Cas 3 : Exécution sécurisée avec vérification

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;

$registry = new AggregateFunctionRegistry();

$functionName = 'COUNT';
$args = ['addresses'];

if ($registry->has($functionName)) {
    $result = $registry->execute($functionName, $data, $args);
    echo "Résultat: $result\n";
} else {
    echo "Fonction $functionName non disponible\n";
}
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Enregistrement d'une fonction avec un nom invalide | `InvalidArgumentException` | `Invalid function name "X". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.` |
| Enregistrement d'une fonction avec un nom déjà utilisé | `InvalidArgumentException` | `Function "X" is already registered. Cannot register duplicate.` |
| Exécution d'une fonction non enregistrée | `InvalidArgumentException` | `Function "X" not registered` |

## Intégration

Le `AggregateFunctionRegistry` est utilisé par :

- **`AggregateEvaluatorService`** : Pour l'exécution des fonctions dans les expressions
- **`AggregateExpressionParser`** : Pour la validation des fonctions lors du parsing
- **`ClusterVOCollection`** : Via les méthodes `whereAggregate()`, `whereAggregateDirect()`, `matchesAggregate()`, `getAggregateValue()`

## Performance

- L'enregistrement des fonctions est O(1) (tableau associatif)
- La résolution des fonctions est O(1)
- Aucun cache interne requis car le registre est un simple stockage de références
- Les fonctions sont instanciées une fois au moment de l'enregistrement

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;
use AndyDefer\LaravelCluster\Functions\CountFunction;

// Création du registre
$registry = new AggregateFunctionRegistry();

// Vérification des fonctions disponibles
$isCountAvailable = $registry->has('COUNT');
$isSumAvailable = $registry->has('SUM');

echo "COUNT disponible: " . ($isCountAvailable ? 'oui' : 'non') . "\n";
echo "SUM disponible: " . ($isSumAvailable ? 'oui' : 'non') . "\n";

// Exécution d'une fonction
$data = [
    'addresses' => ['a', 'b', 'c'],
    'prices' => [10, 20, 30],
    'scores' => [80, 90, 85],
];

$count = $registry->execute('COUNT', $data, ['addresses']);
$sum = $registry->execute('SUM', $data, ['prices']);
$avg = $registry->execute('AVG', $data, ['scores']);

echo "Count: $count\n";  // 3
echo "Sum: $sum\n";      // 60
echo "Avg: $avg\n";      // 85

// Récupération des fonctions booléennes
$booleanFunctions = $registry->getBooleanFunctions();
echo "Fonctions booléennes: " . implode(', ', array_keys($booleanFunctions)) . "\n";

// Enregistrement d'une fonction personnalisée
$customFunction = new class implements AggregateFunctionInterface {
    public function getName(): string { return 'CUSTOM'; }
    public function execute(array $data, array $args): mixed { return 'hello'; }
    public function getMinArgs(): int { return 0; }
    public function getMaxArgs(): int { return PHP_INT_MAX; }
    public function validateArgs(array $args): bool { return true; }
    public function getDefaultValue(): mixed { return ''; }
    public function getReturnType(): string { return 'string'; }
    public function returnsBoolean(): bool { return false; }
};

$registry->register($customFunction);
$result = $registry->execute('CUSTOM', [], []);
echo "Custom result: $result\n"; // hello
```

## Voir aussi

- `AggregateEvaluatorService` - Service d'évaluation des expressions
- `AggregateExpressionParser` - Parser des expressions d'agrégation
- `ClusterVOCollection::whereAggregate()` - Filtrage par expression d'agrégation
- `AbstractAggregateFunction` - Classe de base pour les fonctions d'agrégation