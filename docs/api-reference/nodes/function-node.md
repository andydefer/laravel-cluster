# FunctionNode - Technical Reference

## Description

Représente un nœud de fonction SQL dans l'arbre syntaxique abstrait (AST). Il gère les fonctions d'agrégation (COUNT, SUM, AVG, MIN, MAX) et les fonctions JSON (JSON_LENGTH) appliquées aux chemins de données JSON.

## Hiérarchie

```
Node
    └── FunctionNode
```

## Rôle principal

Évalue les fonctions SQL sur un cluster et génère le SQL correspondant pour différents drivers. Il supporte :

- **Fonctions d'agrégation** : `COUNT`, `SUM`, `AVG`, `MIN`, `MAX`
- **Fonctions JSON** : `JSON_LENGTH`
- **Fonctions de chaîne** : `LENGTH`
- **Évaluation en mémoire** : Sur les données du cluster
- **Génération SQL** : Adaptée à chaque driver

---

## API

### `__construct(string $functionName, string $path, ComparisonOperator $operator, ?string $value = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$functionName` | `string` | Le nom de la fonction (COUNT, SUM, AVG, etc.) |
| `$path` | `string` | Le chemin JSON |
| `$operator` | `ComparisonOperator` | L'opérateur de comparaison |
| `$value` | `string|null` | La valeur de comparaison |

**Exemple :**
```php
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');
$node = new FunctionNode('AVG', 'scores', ComparisonOperator::GREATER_THAN_OR_EQUAL, '85');
$node = new FunctionNode('LENGTH', 'name', ComparisonOperator::EQUAL, '5');
$node = new FunctionNode('JSON_LENGTH', 'addresses', ComparisonOperator::GREATER_THAN, '2');
```

---

### `evaluate(ClusterVO $cluster): bool`

Évalue la fonction contre un cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Le cluster à évaluer |

**Retourne :** `bool` - `true` si la condition est satisfaite

**Exemple :**
```php
$cluster = new ClusterVO(['addresses' => ['a', 'b', 'c']]);
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');
$result = $node->evaluate($cluster); // true
```

---

### `toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

Génère l'expression SQL pour la fonction.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne JSON en base de données |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

**Exemple :**
```php
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');
$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses') > 2
```

---

### `toEloquent(Builder $query, string $column, DatabaseDriver $driver): void`

Applique la fonction à un builder Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Le builder Eloquent |
| `$column` | `string` | La colonne JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Exemple :**
```php
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');
$query = User::query();
$node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);
$users = $query->get();
```

---

### `getChildren(): array`

Retourne les nœuds enfants (vide pour les feuilles).

**Retourne :** `array` - Un tableau vide

---

## Fonctions supportées

| Fonction | Description | Type de retour | SQL généré |
|----------|-------------|----------------|------------|
| `COUNT` | Nombre d'éléments | `int` | `json_array_length` (SQLite), `JSON_LENGTH` (MySQL), `jsonb_array_length` (PostgreSQL) |
| `SUM` | Somme des valeurs | `float` | `CAST(... AS NUMERIC)` (SQLite), `CAST(... AS DECIMAL)` (MySQL), `::numeric` (PostgreSQL) |
| `AVG` | Moyenne des valeurs | `float` | `AVG(CAST(... AS NUMERIC))` |
| `MIN` | Valeur minimale | `float` | `MIN(CAST(... AS NUMERIC))` |
| `MAX` | Valeur maximale | `float` | `MAX(CAST(... AS NUMERIC))` |
| `LENGTH` | Longueur d'une chaîne | `int` | `LENGTH(json_extract(...))` |
| `JSON_LENGTH` | Longueur d'un tableau JSON | `int` | `json_array_length` (SQLite), `JSON_LENGTH` (MySQL), `jsonb_array_length` (PostgreSQL) |

---

## Cas d'utilisation

### Cas 1 : COUNT avec comparaison

```php
<?php

use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

// Plus de 2 adresses
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');

// Évaluation
$cluster = new ClusterVO(['addresses' => ['a', 'b', 'c']]);
$result = $node->evaluate($cluster); // true

// SQL SQLite
$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses') > 2
```

### Cas 2 : AVG avec comparaison

```php
$node = new FunctionNode('AVG', 'scores', ComparisonOperator::GREATER_THAN_OR_EQUAL, '85');

$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// AVG(CAST(json_extract(clusters, '$.scores') AS NUMERIC)) >= 85

$sql = $node->toSql('clusters', DatabaseDriver::MYSQL);
// AVG(CAST(JSON_EXTRACT(clusters, '$.scores') AS DECIMAL(10,2))) >= 85
```

### Cas 3 : LENGTH sur une chaîne

```php
$node = new FunctionNode('LENGTH', 'name', ComparisonOperator::GREATER_THAN, '5');

$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// LENGTH(json_extract(clusters, '$.name')) > 5
```

### Cas 4 : Sans opérateur (COUNT > 0 par défaut)

```php
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '0');
// Utilisé lorsque l'opérateur est omis dans la requête
// "COUNT(addresses)" → COUNT > 0
```

### Cas 5 : Application Eloquent

```php
$query = User::query();

// Filtrage sur le nombre d'adresses
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');
$node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

// Filtrage sur la moyenne des scores
$node2 = new FunctionNode('AVG', 'scores', ComparisonOperator::GREATER_THAN_OR_EQUAL, '85');
$node2->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

$users = $query->get();
```

### Cas 6 : JSON_LENGTH (spécifique SQLite)

```php
$node = new FunctionNode('JSON_LENGTH', 'addresses', ComparisonOperator::GREATER_THAN, '2');

$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses') > 2
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Opérateur non supporté | `InvalidArgumentException` | `Unsupported operator for SQL function` |
| Fonction inconnue | Retourne `1=0` | - |
| SQL expression null | Retourne `1=0` | - |

---

## Performance

- **Évaluation :** O(n) où n est la taille du tableau extrait
- **SQL :** Génération à la volée, pas de cache
- **Eloquent :** Utilisation de `whereRaw` avec des sous-requêtes pour les fonctions d'agrégation

---

## Compatibilité

| Driver | COUNT | SUM/AVG/MIN/MAX | LENGTH | JSON_LENGTH |
|--------|-------|-----------------|--------|-------------|
| SQLite | `json_array_length` | `CAST(... AS NUMERIC)` | `LENGTH(json_extract)` | `json_array_length` |
| MySQL | `JSON_LENGTH` | `CAST(... AS DECIMAL(10,2))` | `LENGTH(JSON_EXTRACT)` | `JSON_LENGTH` |
| PostgreSQL | `jsonb_array_length` | `::numeric` | `LENGTH(->>)` | `jsonb_array_length` |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

// ==================== CRÉATION ====================

$countNode = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');
$sumNode = new FunctionNode('SUM', 'prices', ComparisonOperator::GREATER_THAN, '500');
$avgNode = new FunctionNode('AVG', 'scores', ComparisonOperator::GREATER_THAN_OR_EQUAL, '85');
$minNode = new FunctionNode('MIN', 'scores', ComparisonOperator::LESS_THAN, '75');
$maxNode = new FunctionNode('MAX', 'scores', ComparisonOperator::GREATER_THAN, '90');
$lengthNode = new FunctionNode('LENGTH', 'name', ComparisonOperator::GREATER_THAN, '5');
$jsonLengthNode = new FunctionNode('JSON_LENGTH', 'addresses', ComparisonOperator::GREATER_THAN, '2');

// ==================== ÉVALUATION ====================

$cluster = new ClusterVO([
    'addresses' => ['a', 'b', 'c'],
    'prices' => [100, 200, 300],
    'scores' => [80, 90, 85],
    'name' => 'John Doe',
]);

var_dump($countNode->evaluate($cluster)); // true (3 > 2)
var_dump($sumNode->evaluate($cluster)); // true (600 > 500)
var_dump($avgNode->evaluate($cluster)); // true (85 >= 85)
var_dump($minNode->evaluate($cluster)); // false (80 < 75? non)
var_dump($maxNode->evaluate($cluster)); // false (90 > 90? non)
var_dump($lengthNode->evaluate($cluster)); // true (8 > 5)
var_dump($jsonLengthNode->evaluate($cluster)); // true (3 > 2)

// ==================== GÉNÉRATION SQL ====================

$column = 'clusters';

echo "COUNT (SQLite):\n";
echo $countNode->toSql($column, DatabaseDriver::SQLITE) . "\n";
// json_array_length(clusters, '$.addresses') > 2

echo "COUNT (MySQL):\n";
echo $countNode->toSql($column, DatabaseDriver::MYSQL) . "\n";
// JSON_LENGTH(clusters, '$.addresses') > 2

echo "AVG (SQLite):\n";
echo $avgNode->toSql($column, DatabaseDriver::SQLITE) . "\n";
// AVG(CAST(json_extract(clusters, '$.scores') AS NUMERIC)) >= 85

echo "AVG (MySQL):\n";
echo $avgNode->toSql($column, DatabaseDriver::MYSQL) . "\n";
// AVG(CAST(JSON_EXTRACT(clusters, '$.scores') AS DECIMAL(10,2))) >= 85

echo "LENGTH (SQLite):\n";
echo $lengthNode->toSql($column, DatabaseDriver::SQLITE) . "\n";
// LENGTH(json_extract(clusters, '$.name')) > 5

// ==================== APPLICATION ELOQUENT ====================

$query = User::query();

// Filtrer les utilisateurs avec plus de 2 adresses
$countNode->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

// Filtrer les utilisateurs avec une moyenne >= 85
$avgNode->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

$users = $query->get();
// Utilisateurs avec COUNT(addresses) > 2 ET AVG(scores) >= 85
```

---

## Voir aussi

- `SqlFunctionRegistry` - Registre des fonctions SQL
- `ComparisonOperator` - Énumération des opérateurs
- `DatabaseDriver` - Énumération des drivers
- `Node` - Classe parente
- `GroupNode` - Groupe de conditions
- `ConditionNode` - Condition simple