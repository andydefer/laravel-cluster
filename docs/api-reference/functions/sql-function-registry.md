# SqlFunctionRegistry - Référence Technique

## Description

Registre central gérant l'ensemble des fonctions SQL utilisables dans les requêtes de base de données sur les clusters. Ce registre assure l'enregistrement, la résolution et l'exécution des fonctions SQL avec support multi-drivers (SQLite, MySQL, PostgreSQL).

## Hiérarchie

```
SqlFunctionRegistry
```

## Rôle principal

Le `SqlFunctionRegistry` est le point d'entrée pour toutes les fonctions SQL. Il :

- Gère le cycle de vie des fonctions SQL (enregistrement, résolution, exécution)
- Génère du SQL adapté à chaque driver (SQLite, MySQL, PostgreSQL)
- Supporte l'exécution en mémoire pour les collections
- Valide les noms de fonctions selon la convention SCREAMING_SNAKE_CASE
- Empêche les enregistrements en double
- Sert de pont entre le parseur de requêtes et les fonctions SQL concrètes

## API / Méthodes publiques

### `register(SqlFunctionInterface $function): self`

Enregistre une fonction SQL dans le registre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$function` | `SqlFunctionInterface` | La fonction à enregistrer |

**Retourne :** `self` - L'instance du registre pour le chaînage de méthodes

**Exceptions :**
- `InvalidArgumentException` si une fonction avec le même nom est déjà enregistrée
- `InvalidArgumentException` si le nom de la fonction est invalide (format SCREAMING_SNAKE_CASE)

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;

$registry = new SqlFunctionRegistry();
$customFunction = new CustomFunction();
$registry->register($customFunction);
```

### `has(string $name): bool`

Vérifie si une fonction SQL est enregistrée.

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

### `get(string $name): ?SqlFunctionInterface`

Récupère une fonction SQL enregistrée par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction (insensible à la casse) |

**Retourne :** `SqlFunctionInterface|null` - L'instance de la fonction, ou `null` si non trouvée

**Exemple :**
```php
$countFunction = $registry->get('COUNT');
if ($countFunction !== null) {
    $sql = $countFunction->toSql('clusters', 'addresses', DatabaseDriver::MYSQL);
}
```

### `toSql(string $name, string $column, string $path, DatabaseDriver $driver, array $args = []): ?string`

Génère l'expression SQL pour une fonction enregistrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON dans la colonne |
| `$driver` | `DatabaseDriver` | Le driver de base de données cible |
| `$args` | `array` | Arguments supplémentaires pour la fonction (défaut: `[]`) |

**Retourne :** `string|null` - L'expression SQL, ou `null` si la fonction n'est pas enregistrée

**Exemple :**
```php
$sql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::MYSQL);
// 'JSON_LENGTH(clusters, '$.addresses')'

$sql = $registry->toSql('DISTANCE', 'clusters', 'coordinates', DatabaseDriver::SQLITE, ['coordinates', 48.8566, 2.3522, 'km']);
// Expression SQL avec formule de Haversine
```

### `execute(string $name, mixed $value, array $args = []): mixed`

Exécute une fonction SQL en mémoire sur une valeur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |
| `$value` | `mixed` | La valeur à traiter |
| `$args` | `array` | Arguments supplémentaires pour la fonction (défaut: `[]`) |

**Retourne :** `mixed` - Le résultat de la fonction, ou la valeur originale si la fonction n'est pas enregistrée

**Exemple :**
```php
$result = $registry->execute('COUNT', ['a', 'b', 'c']); // 3
$result = $registry->execute('CONTAINS', ['fr', 'en'], ['fr']); // true
$result = $registry->execute('LENGTH', 'hello'); // 5
```

### `getDefaultValue(string $name): mixed`

Retourne la valeur par défaut d'une fonction SQL.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `mixed` - La valeur par défaut, ou `null` si la fonction n'est pas trouvée

**Exemple :**
```php
$default = $registry->getDefaultValue('COUNT'); // 0
$default = $registry->getDefaultValue('CONTAINS'); // false
$default = $registry->getDefaultValue('DISTANCE'); // 0.0
```

### `validateArgs(string $name, array $args): bool`

Valide les arguments d'une fonction SQL enregistrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |
| `$args` | `array<int, mixed>` | Les arguments à valider |

**Retourne :** `bool` - `true` si les arguments sont valides, `false` sinon

**Exemple :**
```php
$isValid = $registry->validateArgs('CONTAINS', ['languages', 'fr']); // true
$isValid = $registry->validateArgs('CONTAINS', ['languages']); // false (manque le 2ème argument)
```

### `getMinArgs(string $name): ?int`

Retourne le nombre minimum d'arguments requis pour une fonction.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `int|null` - Le nombre minimum d'arguments, ou `null` si la fonction n'est pas trouvée

**Exemple :**
```php
$min = $registry->getMinArgs('COUNT'); // 1
$min = $registry->getMinArgs('CONTAINS'); // 2
```

### `getMaxArgs(string $name): ?int`

Retourne le nombre maximum d'arguments autorisés pour une fonction.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `int|null` - Le nombre maximum d'arguments, ou `null` si la fonction n'est pas trouvée

**Exemple :**
```php
$max = $registry->getMaxArgs('COUNT'); // PHP_INT_MAX (illimité)
$max = $registry->getMaxArgs('CONTAINS'); // 2
```

### `all(): array`

Retourne toutes les fonctions SQL enregistrées.

**Retourne :** `array<string, SqlFunctionInterface>` - Tableau des instances de fonctions indexées par leur nom

**Exemple :**
```php
$allFunctions = $registry->all();
foreach ($allFunctions as $name => $function) {
    echo $name . " : " . get_class($function) . "\n";
}
```

### `getReturnType(string $name): ?string`

Retourne le type de retour d'une fonction SQL enregistrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `string|null` - Le type de retour (`'int'`, `'float'`, `'string'`, `'bool'`), ou `null` si la fonction n'est pas trouvée

**Exemple :**
```php
$type = $registry->getReturnType('COUNT'); // 'int'
$type = $registry->getReturnType('SUM'); // 'float'
$type = $registry->getReturnType('CONTAINS'); // 'bool'
```

### `getNames(): array`

Retourne les noms de toutes les fonctions SQL enregistrées.

**Retourne :** `array<string>` - Tableau des noms de fonctions

**Exemple :**
```php
$names = $registry->getNames();
// ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'LENGTH', 'JSON_LENGTH', 'REGEXP', 'CONTAINS', 'DISTANCE']
```

## Cas d'utilisation

### Cas 1 : Génération SQL multi-drivers

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;

$registry = new SqlFunctionRegistry();

// SQL pour MySQL
$sql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::MYSQL);
// 'JSON_LENGTH(clusters, '$.addresses')'

// SQL pour SQLite
$sql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::SQLITE);
// 'json_array_length(clusters, '$.addresses')'

// SQL pour PostgreSQL
$sql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::PGSQL);
// 'jsonb_array_length(clusters->'addresses')'
```

### Cas 2 : Exécution en mémoire sur des collections

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;

$registry = new SqlFunctionRegistry();

// COUNT sur un tableau
$count = $registry->execute('COUNT', ['a', 'b', 'c']); // 3

// SUM sur des nombres
$sum = $registry->execute('SUM', [10, 20, 30]); // 60.0

// LENGTH sur une chaîne
$length = $registry->execute('LENGTH', 'hello'); // 5

// CONTAINS sur un tableau
$contains = $registry->execute('CONTAINS', ['fr', 'en'], ['fr']); // true

// DISTANCE entre deux coordonnées
$coords = new CoordinatesVO(FloatVO::from(48.8566), FloatVO::from(2.3522));
$distance = $registry->execute('DISTANCE', $coords, ['coordinates', 45.7640, 4.8357, 'km']);
```

### Cas 3 : Validation des arguments

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;

$registry = new SqlFunctionRegistry();

$functionName = 'CONTAINS';

// Validation correcte
if ($registry->validateArgs($functionName, ['languages', 'fr'])) {
    echo "Arguments valides\n";
}

// Validation incorrecte
if (!$registry->validateArgs($functionName, ['languages'])) {
    echo "Arguments invalides - besoin de 2 arguments\n";
}
```

### Cas 4 : Vérification des métadonnées

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;

$registry = new SqlFunctionRegistry();

$functionName = 'DISTANCE';
$minArgs = $registry->getMinArgs($functionName); // 3
$maxArgs = $registry->getMaxArgs($functionName); // 4
$returnType = $registry->getReturnType($functionName); // 'float'
$default = $registry->getDefaultValue($functionName); // 0.0

echo "DISTANCE: min=$minArgs, max=$maxArgs, type=$returnType\n";
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Enregistrement d'une fonction avec un nom invalide | `InvalidArgumentException` | `Invalid function name "X". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.` |
| Enregistrement d'une fonction avec un nom déjà utilisé | `InvalidArgumentException` | `Function "X" is already registered. Cannot register duplicate.` |
| Exécution d'une fonction non enregistrée | `InvalidArgumentException` (via `execute`) | `Function "X" not registered` |

## Intégration

Le `SqlFunctionRegistry` est utilisé par :

- **`Parser`** : Pour la validation des fonctions SQL lors du parsing
- **`FunctionNode`** : Pour l'évaluation des fonctions (SQL et mémoire)
- **`ClusterQuery`** : Pour la génération SQL des fonctions
- **`SqlFunctionRegistry`** : Pour l'enregistrement automatique des fonctions par défaut
- **`DistanceFunction`** : Pour la recherche géographique

## Performance

- L'enregistrement des fonctions est O(1) (tableau associatif)
- La résolution des fonctions est O(1)
- La génération SQL est O(1) - pas de calcul complexe
- Les fonctions sont instanciées une fois au moment de l'enregistrement
- Pas de cache interne nécessaire

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

| Driver | Support |
|--------|---------|
| SQLite 3.9+ | ✅ Complet |
| MySQL 5.7+ | ✅ Complet |
| PostgreSQL 9.4+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\SqlFunctions\DistanceFunction;

// Création du registre
$registry = new SqlFunctionRegistry();

// Vérification des fonctions disponibles
$isCountAvailable = $registry->has('COUNT');
$isDistanceAvailable = $registry->has('DISTANCE');

echo "COUNT disponible: " . ($isCountAvailable ? 'oui' : 'non') . "\n";
echo "DISTANCE disponible: " . ($isDistanceAvailable ? 'oui' : 'non') . "\n";

// Génération SQL pour différents drivers
$column = 'clusters';
$path = 'addresses';

echo "\n--- Génération SQL COUNT ---\n";
echo "MySQL: " . $registry->toSql('COUNT', $column, $path, DatabaseDriver::MYSQL) . "\n";
echo "SQLite: " . $registry->toSql('COUNT', $column, $path, DatabaseDriver::SQLITE) . "\n";
echo "PostgreSQL: " . $registry->toSql('COUNT', $column, $path, DatabaseDriver::PGSQL) . "\n";

// Exécution en mémoire
echo "\n--- Exécution en mémoire ---\n";
$data = ['a', 'b', 'c'];
$count = $registry->execute('COUNT', $data);
echo "COUNT: $count\n"; // 3

$array = [10, 20, 30];
$sum = $registry->execute('SUM', $array);
echo "SUM: $sum\n"; // 60

$array = [10, 20, 30];
$avg = $registry->execute('AVG', $array);
echo "AVG: $avg\n"; // 20

$array = [10, 20, 30];
$min = $registry->execute('MIN', $array);
echo "MIN: $min\n"; // 10

$array = [10, 20, 30];
$max = $registry->execute('MAX', $array);
echo "MAX: $max\n"; // 30

// Validation des arguments
echo "\n--- Validation ---\n";
$isValid = $registry->validateArgs('CONTAINS', ['languages', 'fr']);
echo "CONTAINS avec 2 arguments: " . ($isValid ? 'valide' : 'invalide') . "\n";

$isValid = $registry->validateArgs('CONTAINS', ['languages']);
echo "CONTAINS avec 1 argument: " . ($isValid ? 'valide' : 'invalide') . "\n";

// Métadonnées
echo "\n--- Métadonnées ---\n";
echo "COUNT: min=" . $registry->getMinArgs('COUNT') . ", type=" . $registry->getReturnType('COUNT') . "\n";
echo "CONTAINS: min=" . $registry->getMinArgs('CONTAINS') . ", type=" . $registry->getReturnType('CONTAINS') . "\n";

// Toutes les fonctions
$names = $registry->getNames();
echo "\n--- Fonctions disponibles: " . implode(', ', $names) . "\n";
```

## Voir aussi

- `SqlFunctionInterface` - Interface des fonctions SQL
- `DatabaseDriver` - Énumération des drivers supportés
- `FunctionNode` - Nœud de fonction dans l'AST
- `ClusterQuery` - Service de requêtes
- `DistanceFunction` - Fonction de distance géographique
- `ContainsFunction` - Fonction CONTAINS
- `RegexpFunction` - Fonction REGEXP