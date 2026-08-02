# SQL Functions - Technical Reference

## Description

Les fonctions SQL fournissent des opérations de calcul et d'analyse sur les données JSON directement au niveau de la base de données. Elles génèrent du SQL adapté à chaque driver (SQLite, MySQL, PostgreSQL) et s'exécutent aussi bien en mémoire que dans les requêtes Eloquent.

## Hiérarchie

```
SqlFunctionInterface
    └── AbstractSqlFunction (classe abstraite)
            ├── AvgFunction
            ├── CountFunction
            ├── JsonLengthFunction
            ├── LengthFunction
            ├── MaxFunction
            ├── MinFunction
            ├── RegexpFunction
            ├── SumFunction
            └── ContainsFunction (implémente directement SqlFunctionInterface)
```

## Rôle principal

Les fonctions SQL sont utilisées dans les expressions de requête pour effectuer des calculs sur les données JSON stockées en base de données. Elles génèrent le SQL approprié selon le driver et peuvent également s'exécuter en mémoire pour les collections.

---

# AbstractSqlFunction

## Description

Classe abstraite fournissant les fonctionnalités communes à toutes les fonctions SQL : validation des arguments, valeur par défaut et extraction des nombres.

## API

### `extractNumbers(array $array): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$array` | `array<mixed>` | Le tableau à parcourir |

**Retourne :** `array<float>` - Toutes les valeurs numériques trouvées

### `validateArgs(array $args): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$args` | `array<mixed>` | Les arguments à valider |

**Retourne :** `bool` - `true` si exactement un argument est fourni

### `getDefaultValue(): mixed`

**Retourne :** `int` - La valeur par défaut `0`

### `getMinArgs(): int`

**Retourne :** `int` - Le nombre minimum d'arguments (`1` par défaut)

### `getMaxArgs(): int`

**Retourne :** `int` - Le nombre maximum d'arguments (`PHP_INT_MAX` par défaut)

---

# AvgFunction

## Description

Calcule la moyenne des valeurs numériques dans un tableau JSON.

## API

### `toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value, array $args = []): float`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `float` - La moyenne des valeurs, ou `0.0` si aucune

**Exemple :**
```php
$avg = new AvgFunction();
$avg->execute([10, 20, 30]); // 20.0

// Génération SQL MySQL
$sql = $avg->toSql('clusters', 'scores', DatabaseDriver::MYSQL);
// AVG(CAST(JSON_EXTRACT(clusters, '$.scores') AS DECIMAL(10,2)))
```

---

# CountFunction

## Description

Compte les éléments d'un tableau JSON ou les caractères d'une chaîne.

## API

### `toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value, array $args = []): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `int` - Le nombre d'éléments ou de caractères

**Exemple :**
```php
$count = new CountFunction();
$count->execute(['a', 'b', 'c']); // 3
$count->execute('hello'); // 5

// Génération SQL SQLite
$sql = $count->toSql('clusters', 'addresses', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses')
```

---

# JsonLengthFunction

## Description

Calcule la longueur d'un tableau JSON. Similaire à COUNT mais spécifique aux JSON arrays.

## API

### `toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value, array $args = []): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `int` - Le nombre d'éléments

**Exemple :**
```php
$jsonLength = new JsonLengthFunction();
$jsonLength->execute(['a', 'b', 'c']); // 3

// Génération SQL MySQL
$sql = $jsonLength->toSql('clusters', 'addresses', DatabaseDriver::MYSQL);
// JSON_LENGTH(clusters, '$.addresses')
```

---

# LengthFunction

## Description

Calcule la longueur d'une chaîne ou le nombre d'éléments d'un tableau.

## API

### `toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value, array $args = []): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `int` - La longueur de la chaîne ou le nombre d'éléments

**Exemple :**
```php
$length = new LengthFunction();
$length->execute('hello'); // 5
$length->execute(['a', 'b', 'c']); // 3

// Génération SQL PostgreSQL
$sql = $length->toSql('clusters', 'name', DatabaseDriver::PGSQL);
// LENGTH(clusters->>'name')
```

---

# MaxFunction

## Description

Trouve la valeur numérique maximale dans un tableau JSON.

## API

### `toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value, array $args = []): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `mixed` - La valeur maximale trouvée, ou `0` si aucune

**Exemple :**
```php
$max = new MaxFunction();
$max->execute([10, 30, 20]); // 30.0

// Génération SQL SQLite
$sql = $max->toSql('clusters', 'scores', DatabaseDriver::SQLITE);
// MAX(CAST(json_extract(clusters, '$.scores') AS NUMERIC))
```

---

# MinFunction

## Description

Trouve la valeur numérique minimale dans un tableau JSON.

## API

### `toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value, array $args = []): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `mixed` - La valeur minimale trouvée, ou `0` si aucune

**Exemple :**
```php
$min = new MinFunction();
$min->execute([10, 30, 20]); // 10.0

// Génération SQL MySQL
$sql = $min->toSql('clusters', 'scores', DatabaseDriver::MYSQL);
// MIN(CAST(JSON_EXTRACT(clusters, '$.scores') AS DECIMAL(10,2)))
```

---

# RegexpFunction

## Description

Vérifie si une chaîne correspond à une expression régulière.

Cette fonction fournit des capacités de correspondance regex sur différents drivers de base de données :
- **SQLite** : utilise l'opérateur `REGEXP` (nécessite l'extension REGEXP)
- **MySQL** : utilise l'opérateur `REGEXP`
- **PostgreSQL** : utilise l'opérateur `~` (tilde)

## API

### `toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `string` - L'expression SQL pour extraire la valeur

### `execute(mixed $value, array $args = []): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `mixed` - La valeur si c'est une chaîne, `0` sinon

### `validateArgs(array $args): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$args` | `array<mixed>` | Les arguments à valider |

**Retourne :** `bool` - `true` si exactement deux arguments sont fournis

**Exemples :**
```php
$regexp = new RegexpFunction();

// SQL généré pour MySQL
$sql = $regexp->toSql('clusters', 'name', DatabaseDriver::MYSQL);
// JSON_EXTRACT(clusters, '$.name')

// Utilisation dans une requête
$users = User::whereCluster('clusters', 'REGEXP(name, "^John.*")')->get();

// Avec conditions multiples
$users = User::whereCluster('clusters', 'REGEXP(name, "^John.*") & status=active')->get();
```

---

# SumFunction

## Description

Calcule la somme des valeurs numériques dans un tableau JSON.

## API

### `toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value, array $args = []): float`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |
| `$args` | `array` | Arguments supplémentaires (non utilisés) |

**Retourne :** `float` - La somme des valeurs, ou `0.0` si aucune

**Exemple :**
```php
$sum = new SumFunction();
$sum->execute([10, 20, 30]); // 60.0

// Génération SQL PostgreSQL
$sql = $sum->toSql('clusters', 'prices', DatabaseDriver::PGSQL);
// (clusters->>'prices')::numeric
```

---

# ContainsFunction

## Description

Vérifie si un tableau JSON contient une valeur spécifique. Cette fonction est spéciale car elle retourne un booléen et ne prend pas d'opérateur de comparaison standard.

## API

### `getName(): string`

**Retourne :** `string` - Le nom de la fonction : `'CONTAINS'`

### `toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON vers le tableau |
| `$driver` | `DatabaseDriver` | Le driver de base de données |
| `$args` | `array` | Arguments : `[path, search_value]` |

**Retourne :** `string` - L'expression SQL

**SQL généré :**
| Driver | SQL |
|--------|-----|
| **SQLite** | `EXISTS (SELECT 1 FROM json_each(column, '$.path') WHERE value = 'search')` |
| **MySQL** | `JSON_SEARCH(column, 'one', 'search', NULL, '$."path"') IS NOT NULL` |
| **PostgreSQL** | `EXISTS (SELECT 1 FROM json_array_elements_text(column->'path') AS elem WHERE elem = 'search')` |

### `execute(mixed $value, array $args = []): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter (doit être un tableau) |
| `$args` | `array` | Arguments : `[path, search_value]` |

**Retourne :** `bool` - `true` si la valeur est trouvée dans le tableau

### `validateArgs(array $args): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$args` | `array<mixed>` | Les arguments à valider |

**Retourne :** `bool` - `true` si exactement 2 arguments sont fournis (path + valeur)

### `getMinArgs(): int`

**Retourne :** `int` - `2` (exige exactement 2 arguments)

### `getMaxArgs(): int`

**Retourne :** `int` - `2` (exige exactement 2 arguments)

**Exemple :**
```php
use AndyDefer\LaravelCluster\SqlFunctions\ContainsFunction;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$contains = new ContainsFunction();

// Évaluation en mémoire
$result = $contains->execute(['fr', 'en', 'es'], ['languages', 'fr']); // true

// Génération SQL SQLite
$sql = $contains->toSql('clusters', 'languages', DatabaseDriver::SQLITE, ['languages', 'fr']);
// "EXISTS (SELECT 1 FROM json_each(clusters, '$.languages') WHERE value = 'fr')"

// Génération SQL MySQL
$sql = $contains->toSql('clusters', 'languages', DatabaseDriver::MYSQL, ['languages', 'fr']);
// "JSON_SEARCH(clusters, 'one', 'fr', NULL, '$.\"languages\"') IS NOT NULL"

// Validation des arguments
$contains->validateArgs(['languages', 'fr']); // true
$contains->validateArgs(['fr']); // false
```

---

## Cas d'utilisation

### Cas 1 : Filtrer les clusters avec plus de 2 adresses (SQLite)

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$clusterQuery = new ClusterQuery;
$query = TestCluster::query();

$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'COUNT(addresses) > 2',
    DatabaseDriver::SQLITE
);

$results = $query->get();
// Seuls les clusters avec plus de 2 adresses
```

### Cas 2 : Calculer la moyenne des scores en MySQL

```php
$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'AVG(scores) >= 85',
    DatabaseDriver::MYSQL
);
```

### Cas 3 : Utiliser JSON_LENGTH pour la compatibilité

```php
$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'JSON_LENGTH(addresses) > 2',
    DatabaseDriver::SQLITE
);
// json_array_length(clusters, '$.addresses') > 2
```

### Cas 4 : Filtrage sur la longueur d'une chaîne

```php
$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'LENGTH(name) > 5',
    DatabaseDriver::PGSQL
);
// LENGTH(clusters->>'name') > 5
```

### Cas 5 : Filtrage avec expression régulière

```php
// Utilisateurs dont le nom commence par "John"
$users = User::whereCluster('clusters', 'REGEXP(name, "^John.*")')->get();

// Utilisateurs dont l'email est Gmail
$users = User::whereCluster('clusters', 'REGEXP(email, ".*@gmail\.com$")')->get();

// Utilisateurs avec un nom contenant des lettres uniquement
$users = User::whereCluster('clusters', 'REGEXP(name, "^[A-Za-z]+$")')->get();

// Combinaison avec d'autres conditions
$users = User::whereCluster('clusters', 'REGEXP(name, "^John.*") & status=active')->get();
```

### Cas 6 : Utilisation de CONTAINS avec Eloquent

```php
use App\Models\User;

// Utilisateurs qui parlent français
$users = User::whereCluster('clusters', 'CONTAINS(languages, fr)')->get();

// Utilisateurs qui parlent français ET anglais
$users = User::whereCluster('clusters', 'CONTAINS(languages, fr) & CONTAINS(languages, en)')->get();

// Utilisateurs qui parlent français OU anglais
$users = User::whereCluster('clusters', 'CONTAINS(languages, fr) | CONTAINS(languages, en)')->get();

// Utilisateurs qui parlent français ET sont actifs
$users = User::whereCluster('clusters', 'CONTAINS(languages, fr) & status=active')->get();

// Utilisateurs qui parlent français = true
$users = User::whereCluster('clusters', 'CONTAINS(languages, fr) = true')->get();

// Utilisateurs qui parlent français = false (ne parlent pas français)
$users = User::whereCluster('clusters', 'CONTAINS(languages, fr) = false')->get();
```

### Cas 7 : Utilisation de CONTAINS avec ClusterVOCollection

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John Doe',
    'languages' => ['fr', 'en', 'es'],
    'status' => 'active',
]));
$collection->add(new ClusterVO([
    'name' => 'Jane Smith',
    'languages' => ['en', 'de'],
    'status' => 'active',
]));
$collection->add(new ClusterVO([
    'name' => 'Bob Johnson',
    'languages' => ['fr', 'it'],
    'status' => 'inactive',
]));

// Filtrage avec CONTAINS
$frenchSpeakers = $collection->whereQuery('CONTAINS(languages, fr)');
// John Doe, Bob Johnson

// Filtrage avec CONTAINS ET condition
$activeFrenchSpeakers = $collection->whereQuery('CONTAINS(languages, fr) & status=active');
// John Doe uniquement

// Filtrage avec CONTAINS ET OR
$frenchOrEnglish = $collection->whereQuery('CONTAINS(languages, fr) | CONTAINS(languages, en)');
// John Doe, Jane Smith, Bob Johnson
```

### Cas 8 : Utilisation avec les valeurs booléennes 'yes'/'no'

```php
use App\Models\User;

// Utilisateurs vérifiés
$users = User::whereCluster('clusters', 'verified=yes')->get();

// Utilisateurs non vérifiés
$users = User::whereCluster('clusters', 'verified=no')->get();

// Utilisateurs actifs et vérifiés
$users = User::whereCluster('clusters', 'status=active & verified=yes')->get();

// Utilisateurs avec une moyenne de scores >= 85
$users = User::whereCluster('clusters', 'AVG(scores) >= 85 & verified=yes')->get();

// Utilisateurs avec une moyenne de scores >= 85 et non vérifiés
$users = User::whereCluster('clusters', 'AVG(scores) >= 85 & verified=no')->get();

// Utilisateurs qui parlent français ET sont vérifiés
$users = User::whereCluster('clusters', 'CONTAINS(languages, fr) & verified=yes')->get();
```

### Cas 9 : Utilisation avec ClusterVOCollection et booléens

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John Doe',
    'status' => 'active',
    'verified' => 'yes',
    'scores' => [80, 90, 85],
    'languages' => ['fr', 'en'],
]));
$collection->add(new ClusterVO([
    'name' => 'Jane Smith',
    'status' => 'active',
    'verified' => 'no',
    'scores' => [70, 75, 80],
    'languages' => ['en', 'de'],
]));
$collection->add(new ClusterVO([
    'name' => 'Bob Johnson',
    'status' => 'inactive',
    'verified' => 'yes',
    'scores' => [95, 98, 92],
    'languages' => ['fr', 'it'],
]));

// Filtrage avec valeurs booléennes
$activeVerified = $collection->whereQuery('status=active & verified=yes');
// John Doe uniquement

// Filtrage avec fonction d'agrégation
$highScores = $collection->whereAggregate('{AVG(scores) >= 85} & verified=yes');
// John Doe, Bob Johnson

// Filtrage avec CONTAINS
$frenchSpeakers = $collection->whereQuery('CONTAINS(languages, fr)');
// John Doe, Bob Johnson

// Filtrage avec CONTAINS ET booléen
$frenchAndVerified = $collection->whereQuery('CONTAINS(languages, fr) & verified=yes');
// John Doe, Bob Johnson

// Filtrage avec NOT
$notVerified = $collection->whereQuery('!verified');
// Jane Smith (verified=no)

// Filtrage avec NOT sur la valeur
$notActive = $collection->whereQuery('status!=active');
// Bob Johnson

// Filtrage avec whereYes et whereNo
$verified = $collection->whereYes('verified');
// John Doe, Bob Johnson
$unverified = $collection->whereNo('verified');
// Jane Smith
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fonction inconnue | `InvalidArgumentException` | `Function "{name}" not registered` |
| Driver non supporté | Retourne `1=0` (condition toujours fausse) | - |
| Arguments invalides pour REGEXP | Retourne `false` | - |
| CONTAINS avec moins de 2 arguments | Retourne `false` dans `execute()` | - |
| CONTAINS avec path vide | `validateArgs()` retourne `false` | - |

---

## Intégration

Les fonctions SQL sont utilisées par :

- **`SqlFunctionRegistry`** : Enregistrement et résolution des fonctions
- **`FunctionNode`** : Génération SQL et évaluation en mémoire
- **`ClusterQuery`** : Application des requêtes aux builders Eloquent
- **`ClusterServiceProvider`** : Enregistrement des fonctions SQLite personnalisées

---

## Drivers supportés

| Driver | JSON Extraction | COUNT | SUM/AVG/MIN/MAX | LENGTH | REGEXP | CONTAINS |
|--------|-----------------|-------|-----------------|--------|--------|----------|
| **SQLite** | `json_extract()` | `json_array_length()` | `CAST(... AS NUMERIC)` | `LENGTH(json_extract())` | `REGEXP` | `json_each()` |
| **MySQL** | `JSON_EXTRACT()` | `JSON_LENGTH()` | `CAST(... AS DECIMAL(10,2))` | `LENGTH(JSON_EXTRACT())` | `REGEXP` | `JSON_SEARCH()` |
| **PostgreSQL** | `->>` | `jsonb_array_length()` | `::numeric` | `LENGTH(->>)` | `~` | `json_array_elements_text()` |

---

## Performance

- **Complexité :** O(n) où n est le nombre d'éléments dans le tableau JSON
- **Extraction des nombres :** Récursive, peut être coûteuse pour des structures profondément imbriquées
- **Cache :** Les expressions SQL sont générées à la volée
- **Recommandation :** Utiliser les fonctions SQL directement dans les requêtes pour de grands volumes de données, plutôt que d'utiliser les fonctions d'agrégation en mémoire

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

| Version Database | Support |
|------------------|---------|
| SQLite 3.9+ | ✅ Complet (REGEXP nécessite l'extension) |
| MySQL 5.7+ | ✅ Complet |
| PostgreSQL 9.4+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\SqlFunctions\CountFunction;
use AndyDefer\LaravelCluster\SqlFunctions\AvgFunction;
use AndyDefer\LaravelCluster\SqlFunctions\RegexpFunction;
use AndyDefer\LaravelCluster\SqlFunctions\ContainsFunction;

// Création de l'instance
$clusterQuery = new ClusterQuery;

// Utilisation directe des fonctions
$count = new CountFunction();
$sql = $count->toSql('clusters', 'addresses', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses')

$avg = new AvgFunction();
$sql = $avg->toSql('clusters', 'scores', DatabaseDriver::MYSQL);
// AVG(CAST(JSON_EXTRACT(clusters, '$.scores') AS DECIMAL(10,2)))

$regexp = new RegexpFunction();
$sql = $regexp->toSql('clusters', 'name', DatabaseDriver::MYSQL);
// JSON_EXTRACT(clusters, '$.name')

$contains = new ContainsFunction();
$sql = $contains->toSql('clusters', 'languages', DatabaseDriver::SQLITE, ['languages', 'fr']);
// EXISTS (SELECT 1 FROM json_each(clusters, '$.languages') WHERE value = 'fr')

// Application à une requête Eloquent
$query = TestCluster::query();

// Filtrage avec COUNT
$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'COUNT(addresses) > 2',
    DatabaseDriver::SQLITE
);

// Filtrage avec AVG
$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'AVG(scores) >= 85',
    DatabaseDriver::MYSQL
);

// Filtrage avec LENGTH
$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'LENGTH(name) > 5',
    DatabaseDriver::PGSQL
);

// Filtrage avec REGEXP
$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'REGEXP(name, "^John.*")',
    DatabaseDriver::MYSQL
);

// Filtrage avec CONTAINS
$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'CONTAINS(languages, fr)',
    DatabaseDriver::SQLITE
);

// Expression combinée avec valeurs booléennes et CONTAINS
$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'COUNT(addresses) > 1 & AVG(scores) >= 80 & verified=yes & CONTAINS(languages, fr)',
    DatabaseDriver::SQLITE
);

$results = $query->get();
```

---

## Voir aussi

- [`SqlFunctionRegistry`](Registry/SqlFunctionRegistry.md) - Registre des fonctions SQL
- [`FunctionNode`](Nodes/FunctionNode.md) - Nœud de fonction dans l'AST
- [`ClusterQuery`](ClusterQuery.md) - Service de requêtes
- [`DatabaseDriver`](Enums/DatabaseDriver.md) - Énumération des drivers supportés
- [`ContainsFunction`](SqlFunctions/ContainsFunction.md) - Fonction CONTAINS détaillée