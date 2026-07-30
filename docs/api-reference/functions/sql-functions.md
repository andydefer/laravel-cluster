# SQL Functions - Technical Reference

## Description

Les fonctions SQL fournissent des opérations de calcul et d'analyse sur les données JSON directement au niveau de la base de données. Elles génèrent du SQL adapté à chaque driver (SQLite, MySQL, PostgreSQL) et s'exécutent aussi bien en mémoire que dans les requêtes Eloquent.

## Hiérarchie

```
SqlFunctionInterface
    └── AbstractSqlFunction
            ├── AvgFunction
            ├── CountFunction
            ├── JsonLengthFunction
            ├── LengthFunction
            ├── MaxFunction
            ├── MinFunction
            └── SumFunction
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

---

# AvgFunction

## Description

Calcule la moyenne des valeurs numériques dans un tableau JSON.

## API

### `toSql(string $column, string $path, DatabaseDriver $driver): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value): float`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |

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

### `toSql(string $column, string $path, DatabaseDriver $driver): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |

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

### `toSql(string $column, string $path, DatabaseDriver $driver): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |

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

### `toSql(string $column, string $path, DatabaseDriver $driver): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |

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

### `toSql(string $column, string $path, DatabaseDriver $driver): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |

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

### `toSql(string $column, string $path, DatabaseDriver $driver): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |

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

# SumFunction

## Description

Calcule la somme des valeurs numériques dans un tableau JSON.

## API

### `toSql(string $column, string $path, DatabaseDriver $driver): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

### `execute(mixed $value): float`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `mixed` | La valeur à traiter |

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

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fonction inconnue | `InvalidArgumentException` | `Function "{name}" not registered` |
| Driver non supporté | Retourne `1=0` (condition toujours fausse) | - |

---

## Intégration

Les fonctions SQL sont utilisées par :

- **`SqlFunctionRegistry`** : Enregistrement et résolution des fonctions
- **`FunctionNode`** : Génération SQL et évaluation en mémoire
- **`ClusterQuery`** : Application des requêtes aux builders Eloquent
- **`ClusterServiceProvider`** : Enregistrement des fonctions SQLite personnalisées

---

## Drivers supportés

| Driver | JSON Extraction | COUNT | SUM/AVG/MIN/MAX | LENGTH |
|--------|-----------------|-------|-----------------|--------|
| **SQLite** | `json_extract()` | `json_array_length()` | `CAST(... AS NUMERIC)` | `LENGTH(json_extract())` |
| **MySQL** | `JSON_EXTRACT()` | `JSON_LENGTH()` | `CAST(... AS DECIMAL(10,2))` | `LENGTH(JSON_EXTRACT())` |
| **PostgreSQL** | `->>` | `jsonb_array_length()` | `::numeric` | `LENGTH(->>)` |

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
| SQLite 3.9+ | ✅ Complet |
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

// Création de l'instance
$clusterQuery = new ClusterQuery;

// Utilisation directe des fonctions
$count = new CountFunction();
$sql = $count->toSql('clusters', 'addresses', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses')

$avg = new AvgFunction();
$sql = $avg->toSql('clusters', 'scores', DatabaseDriver::MYSQL);
// AVG(CAST(JSON_EXTRACT(clusters, '$.scores') AS DECIMAL(10,2)))

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

// Expression combinée
$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'COUNT(addresses) > 1 & AVG(scores) >= 80',
    DatabaseDriver::SQLITE
);

$results = $query->get();
```

---

## Voir aussi

- `SqlFunctionRegistry` - Registre des fonctions SQL
- `FunctionNode` - Nœud de fonction dans l'AST
- `ClusterQuery` - Service de requêtes
- `DatabaseDriver` - Énumération des drivers supportés