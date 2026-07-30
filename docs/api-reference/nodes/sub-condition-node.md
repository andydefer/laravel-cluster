# SubConditionNode - Technical Reference

## Description

Représente un nœud de sous-condition dans l'arbre syntaxique abstrait (AST). Il gère les conditions sur les tableaux JSON imbriqués, permettant des requêtes comme `addresses[city=Kinshasa]` en évaluant la condition contre chaque élément du tableau.

## Hiérarchie

```
Node
    └── SubConditionNode
```

## Rôle principal

Permet d'évaluer des conditions sur des tableaux d'objets stockés dans des colonnes JSON. Il supporte :

- **Conditions simples** : `addresses[city=Kinshasa]`
- **Conditions avec AND/OR** : `addresses[city=Kinshasa & country=RDC]`
- **EXISTS / NOT_EXISTS** : `addresses[]` ou `addresses[#city]`
- **LIKE / NOT_LIKE** : `addresses[city=~kin%]`
- **Chemins imbriqués** : `settings.notifications[email=true]`

---

## API

### `__construct(string $path, NodeInterface $condition)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Le chemin vers le tableau JSON |
| `$condition` | `NodeInterface` | La condition à appliquer à chaque élément |

**Exemple :**
```php
$condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
$node = new SubConditionNode('addresses', $condition);
```

---

### `getPath(): string`

Retourne le chemin de la sous-condition.

**Retourne :** `string` - Le chemin

---

### `getCondition(): NodeInterface`

Retourne la condition de la sous-condition.

**Retourne :** `NodeInterface` - La condition

---

### `evaluate(ClusterVO $data): bool`

Évalue la sous-condition contre un cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `ClusterVO` | Le cluster à évaluer |

**Retourne :** `bool` - `true` si la sous-condition est satisfaite

**Exemple :**
```php
$cluster = new ClusterVO([
    'addresses' => [
        ['city' => 'Kinshasa'],
        ['city' => 'Paris'],
    ],
]);
$condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
$node = new SubConditionNode('addresses', $condition);
$result = $node->evaluate($cluster); // true
```

---

### `toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

Génère l'expression SQL pour la sous-condition.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne JSON en base de données |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

**Exemple :**
```php
$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE LOWER(json_extract(value, '$.city')) = LOWER('Kinshasa'))
```

---

### `toEloquent(Builder $query, string $column, DatabaseDriver $driver): void`

Applique la sous-condition à un builder Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Le builder Eloquent |
| `$column` | `string` | La colonne JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Exemple :**
```php
$query = User::query();
$node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);
$users = $query->get();
```

---

### `getChildren(): array`

Retourne les nœuds enfants de la sous-condition.

**Retourne :** `array<NodeInterface>` - Le nœud de condition

---

## Cas spéciaux

### Condition `__empty__`

Vérifie que le tableau n'est pas vide.

```php
$node = new SubConditionNode('addresses', new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'true'));
// Vérifie que addresses n'est pas vide
```

**SQL généré :**
```sql
-- SQLite
json_array_length(clusters, '$.addresses') > 0
-- MySQL
JSON_LENGTH(clusters, '$.addresses') > 0
-- PostgreSQL
jsonb_array_length(clusters->'addresses') > 0
```

### Condition `*` (wildcard EXISTS)

Vérifie l'existence d'au moins un élément dans le tableau.

```php
$node = new SubConditionNode('addresses', new ConditionNode('*', ComparisonOperator::EXISTS));
// Vérifie que addresses contient au moins un élément
```

**SQL généré :**
```sql
-- SQLite
EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses'))
-- MySQL
EXISTS (SELECT 1 FROM JSON_TABLE(clusters, '$.addresses[*]' COLUMNS(value JSON PATH '$')) AS jt)
-- PostgreSQL
EXISTS (SELECT 1 FROM jsonb_array_elements(clusters->'addresses') AS value)
```

### Condition `NOT_EXISTS`

Vérifie l'absence d'éléments correspondant à la condition.

```php
$node = new SubConditionNode('addresses', new ConditionNode('city', ComparisonOperator::NOT_EXISTS));
// Vérifie qu'il existe une adresse sans la clé 'city'
```

---

## Drivers supportés

| Driver | Fonction JSON | Syntaxe |
|--------|---------------|---------|
| **SQLite** | `json_each()` | `EXISTS (SELECT 1 FROM json_each(column, '$.path') WHERE condition)` |
| **MySQL** | `JSON_TABLE()` | `EXISTS (SELECT 1 FROM JSON_TABLE(column, '$.path[*]' COLUMNS(value JSON PATH '$')) AS jt WHERE condition)` |
| **PostgreSQL** | `jsonb_array_elements()` | `EXISTS (SELECT 1 FROM jsonb_array_elements(column->'path') AS value WHERE condition)` |

---

## Cas d'utilisation

### Cas 1 : Condition simple sur un tableau

```php
$condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
$node = new SubConditionNode('addresses', $condition);

$cluster = new ClusterVO([
    'addresses' => [
        ['city' => 'Kinshasa'],
        ['city' => 'Paris'],
    ],
]);
$result = $node->evaluate($cluster); // true
```

### Cas 2 : Condition avec AND

```php
$cityCondition = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
$countryCondition = new ConditionNode('country', ComparisonOperator::EQUAL, 'RDC');
$andNode = new GroupNode(LogicalOperator::AND, $cityCondition, $countryCondition);
$node = new SubConditionNode('addresses', $andNode);
// addresses[city=Kinshasa & country=RDC]
```

### Cas 3 : Condition avec OR

```php
$city1 = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
$city2 = new ConditionNode('city', ComparisonOperator::EQUAL, 'Paris');
$orNode = new GroupNode(LogicalOperator::OR, $city1, $city2);
$node = new SubConditionNode('addresses', $orNode);
// addresses[city=Kinshasa | city=Paris]
```

### Cas 4 : LIKE dans une sous-condition

```php
$condition = new ConditionNode('city', ComparisonOperator::LIKE, 'kin%');
$node = new SubConditionNode('addresses', $condition);
// addresses[city=~kin%]
```

### Cas 5 : Chemin imbriqué

```php
$condition = new ConditionNode('email', ComparisonOperator::EQUAL, 'true');
$node = new SubConditionNode('settings.notifications', $condition);
// settings.notifications[email=true]
```

### Cas 6 : Application Eloquent

```php
$query = User::query();
$node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);
$users = $query->get();
// Utilisateurs ayant une adresse à Kinshasa
```

---

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Chemin inexistant | `evaluate()` retourne `false` |
| Valeur non tableau | `evaluate()` retourne `false` |
| Sous-condition vide | Vérifie l'existence du tableau |
| Condition NOT_EXISTS | Vérifie l'absence de la clé |

---

## Performance

- **Évaluation :** O(n) où n est le nombre d'éléments dans le tableau
- **SQL :** Utilisation de sous-requêtes EXISTS pour l'efficacité
- **Eloquent :** Utilisation de `whereRaw` avec des sous-requêtes

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

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

// ==================== CRÉATION ====================

// Simple
$simpleNode = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa')
);

// Avec AND
$andNode = new SubConditionNode(
    'addresses',
    new GroupNode(
        LogicalOperator::AND,
        new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa'),
        new ConditionNode('country', ComparisonOperator::EQUAL, 'RDC')
    )
);

// Avec OR
$orNode = new SubConditionNode(
    'addresses',
    new GroupNode(
        LogicalOperator::OR,
        new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa'),
        new ConditionNode('city', ComparisonOperator::EQUAL, 'Paris')
    )
);

// LIKE
$likeNode = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::LIKE, 'kin%')
);

// EXISTS (vide)
$existsNode = new SubConditionNode(
    'addresses',
    new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'true')
);

// Chemin imbriqué
$nestedNode = new SubConditionNode(
    'settings.notifications',
    new ConditionNode('email', ComparisonOperator::EQUAL, 'true')
);

// ==================== ÉVALUATION ====================

$cluster = new ClusterVO([
    'addresses' => [
        ['city' => 'Kinshasa', 'country' => 'RDC'],
        ['city' => 'Paris', 'country' => 'France'],
    ],
    'settings' => [
        'notifications' => [
            ['email' => 'true', 'sms' => 'false'],
        ],
    ],
]);

var_dump($simpleNode->evaluate($cluster)); // true
var_dump($andNode->evaluate($cluster)); // true
var_dump($orNode->evaluate($cluster)); // true
var_dump($likeNode->evaluate($cluster)); // true
var_dump($existsNode->evaluate($cluster)); // true
var_dump($nestedNode->evaluate($cluster)); // true

// ==================== GÉNÉRATION SQL ====================

$column = 'clusters';

echo "Simple (SQLite):\n";
echo $simpleNode->toSql($column, DatabaseDriver::SQLITE) . "\n";
// EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE LOWER(json_extract(value, '$.city')) = LOWER('Kinshasa'))

echo "\nAND (SQLite):\n";
echo $andNode->toSql($column, DatabaseDriver::SQLITE) . "\n";
// EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE LOWER(json_extract(value, '$.city')) = LOWER('Kinshasa') AND LOWER(json_extract(value, '$.country')) = LOWER('RDC'))

echo "\nEXISTS (SQLite):\n";
echo $existsNode->toSql($column, DatabaseDriver::SQLITE) . "\n";
// json_array_length(clusters, '$.addresses') > 0

// ==================== APPLICATION ELOQUENT ====================

$query = User::query();

// Filtrer les utilisateurs avec une adresse à Kinshasa
$simpleNode->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

// Filtrer les utilisateurs avec une notification email=true
$nestedNode->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

$users = $query->get();
// Utilisateurs avec une adresse à Kinshasa ET une notification email=true
```

---

## Voir aussi

- `ConditionNode` - Condition simple
- `GroupNode` - Groupe de conditions
- `ComparisonOperator` - Énumération des opérateurs
- `DatabaseDriver` - Énumération des drivers
- `Node` - Interface des nœuds