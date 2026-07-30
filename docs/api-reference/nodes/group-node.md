# GroupNode - Technical Reference

## Description

Regroupe plusieurs nœuds de condition avec un opérateur logique. Il supporte les opérations binaires (AND, OR) avec plusieurs enfants et les opérations unaires (NOT) avec un seul enfant.

## Hiérarchie

```
Node
    └── GroupNode
```

## Rôle principal

Permet la construction d'expressions logiques complexes en combinant des conditions simples avec des opérateurs AND, OR et NOT. Assure la bonne évaluation et la génération SQL avec la priorité des opérateurs respectée.

---

## API

### `__construct(LogicalOperator $operator, NodeInterface ...$children)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$operator` | `LogicalOperator` | L'opérateur logique (AND, OR, NOT) |
| `...$children` | `NodeInterface` | Les nœuds enfants à grouper |

**Exemple :**
```php
// AND - deux conditions
$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);

// OR - trois conditions
$group = new GroupNode(
    LogicalOperator::OR,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('status', ComparisonOperator::EQUAL, 'pending'),
    new ConditionNode('status', ComparisonOperator::EQUAL, 'inactive')
);

// NOT - une condition
$group = new GroupNode(
    LogicalOperator::NOT,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'inactive')
);
```

---

### `getOperator(): LogicalOperator`

Retourne l'opérateur logique du groupe.

**Retourne :** `LogicalOperator` - L'opérateur

---

### `evaluate(ClusterVO $data): bool`

Évalue le groupe contre un cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `ClusterVO` | Le cluster à évaluer |

**Retourne :** `bool` - `true` si la condition est satisfaite

| Groupe vide | Résultat |
|-------------|----------|
| AND | `true` |
| OR | `false` |
| NOT | `false` |

**Exemple :**
```php
$cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);
$group = new GroupNode(LogicalOperator::AND, $statusNode, $roleNode);
$result = $group->evaluate($cluster); // true
```

---

### `toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

Génère l'expression SQL pour le groupe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne JSON en base de données |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

**Exemple :**
```php
$sql = $group->toSql('clusters', DatabaseDriver::SQLITE);
// (LOWER(json_extract(clusters, '$.status')) = LOWER('active') AND LOWER(json_extract(clusters, '$.role')) = LOWER('admin'))
```

---

### `toEloquent(Builder $query, string $column, DatabaseDriver $driver): void`

Applique le groupe à un builder Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Le builder Eloquent |
| `$column` | `string` | La colonne JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Exemple :**
```php
$query = User::query();
$group->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);
$users = $query->get();
```

---

### `getChildren(): array`

Retourne les nœuds enfants du groupe.

**Retourne :** `array<int, NodeInterface>` - Les enfants

---

## Comportement par opérateur

### AND

| Enfants | Évaluation | SQL |
|---------|------------|-----|
| 2+ | Tous doivent être `true` | `(cond1 AND cond2 AND ...)` |
| 1 | Idem que l'enfant | `cond1` |
| 0 | `true` | `1=1` |

**Exemple :**
```php
$group = new GroupNode(
    LogicalOperator::AND,
    $cond1,
    $cond2,
    $cond3
);
// Équivalent: $cond1 && $cond2 && $cond3
// SQL: (cond1 AND cond2 AND cond3)
```

### OR

| Enfants | Évaluation | SQL |
|---------|------------|-----|
| 2+ | Au moins un `true` | `(cond1 OR cond2 OR ...)` |
| 1 | Idem que l'enfant | `cond1` |
| 0 | `false` | `1=0` |

**Exemple :**
```php
$group = new GroupNode(
    LogicalOperator::OR,
    $cond1,
    $cond2,
    $cond3
);
// Équivalent: $cond1 || $cond2 || $cond3
// SQL: (cond1 OR cond2 OR cond3)
```

### NOT

| Enfants | Évaluation | SQL |
|---------|------------|-----|
| 1 | Négation de l'enfant | `NOT (cond)` |
| 0 | `false` | `1=0` |
| >1 | Seul le premier est utilisé | - |

**Exemple :**
```php
$group = new GroupNode(
    LogicalOperator::NOT,
    $cond
);
// Équivalent: !$cond
// SQL: NOT (cond)
```

---

## Cas d'utilisation

### Cas 1 : AND avec deux conditions

```php
$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);

$cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);
$result = $group->evaluate($cluster); // true

$sql = $group->toSql('clusters', DatabaseDriver::SQLITE);
// (LOWER(json_extract(clusters, '$.status')) = LOWER('active') AND LOWER(json_extract(clusters, '$.role')) = LOWER('admin'))
```

### Cas 2 : OR avec trois conditions

```php
$group = new GroupNode(
    LogicalOperator::OR,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('status', ComparisonOperator::EQUAL, 'pending'),
    new ConditionNode('status', ComparisonOperator::EQUAL, 'inactive')
);
// Vérifie si le statut est actif, en attente ou inactif
```

### Cas 3 : NOT

```php
$group = new GroupNode(
    LogicalOperator::NOT,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'inactive')
);
// Vérifie que le statut n'est pas inactif
```

### Cas 4 : Groupes imbriqués

```php
$innerGroup = new GroupNode(
    LogicalOperator::OR,
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'doctor')
);

$outerGroup = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    $innerGroup
);
// (status=active AND (role=admin OR role=doctor))
```

### Cas 5 : Application Eloquent

```php
$query = User::query();

// Groupe AND
$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);
$group->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

// Groupe OR
$orGroup = new GroupNode(
    LogicalOperator::OR,
    new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'true'),
    new ConditionNode('lang_en', ComparisonOperator::EQUAL, 'true')
);
$orGroup->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

$users = $query->get();
// status=active AND role=admin AND (lang_fr=true OR lang_en=true)
```

---

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Groupe AND vide | Évalue à `true`, SQL `1=1` |
| Groupe OR vide | Évalue à `false`, SQL `1=0` |
| Groupe NOT vide | Évalue à `false`, SQL `1=0` |
| Groupe NOT avec >1 enfant | Seul le premier enfant est utilisé |

---

## Performance

- **Évaluation :** O(n) où n est le nombre d'enfants
- **SQL :** Génération à la volée, pas de cache
- **Eloquent :** Utilisation de sous-requêtes pour maintenir la priorité des opérateurs

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

use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

// ==================== CRÉATION ====================

// Groupe AND simple
$andGroup = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);

// Groupe OR
$orGroup = new GroupNode(
    LogicalOperator::OR,
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'doctor'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'guest')
);

// Groupe NOT
$notGroup = new GroupNode(
    LogicalOperator::NOT,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'inactive')
);

// Groupe imbriqué
$nestedGroup = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new GroupNode(
        LogicalOperator::OR,
        new ConditionNode('role', ComparisonOperator::EQUAL, 'admin'),
        new ConditionNode('role', ComparisonOperator::EQUAL, 'doctor')
    ),
    new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2')
);

// ==================== ÉVALUATION ====================

$cluster = new ClusterVO([
    'status' => 'active',
    'role' => 'admin',
    'addresses' => ['a', 'b', 'c'],
]);

var_dump($andGroup->evaluate($cluster)); // true
var_dump($orGroup->evaluate($cluster)); // true
var_dump($notGroup->evaluate($cluster)); // true
var_dump($nestedGroup->evaluate($cluster)); // true

// ==================== GÉNÉRATION SQL ====================

$column = 'clusters';

echo "AND Group:\n";
echo $andGroup->toSql($column, DatabaseDriver::SQLITE) . "\n";
// (LOWER(json_extract(clusters, '$.status')) = LOWER('active') AND LOWER(json_extract(clusters, '$.role')) = LOWER('admin'))

echo "\nOR Group:\n";
echo $orGroup->toSql($column, DatabaseDriver::SQLITE) . "\n";
// (LOWER(json_extract(clusters, '$.role')) = LOWER('admin') OR LOWER(json_extract(clusters, '$.role')) = LOWER('doctor') OR LOWER(json_extract(clusters, '$.role')) = LOWER('guest'))

echo "\nNOT Group:\n";
echo $notGroup->toSql($column, DatabaseDriver::SQLITE) . "\n";
// NOT (LOWER(json_extract(clusters, '$.status')) = LOWER('inactive'))

echo "\nNested Group:\n";
echo $nestedGroup->toSql($column, DatabaseDriver::SQLITE) . "\n";
// (LOWER(json_extract(clusters, '$.status')) = LOWER('active') AND (LOWER(json_extract(clusters, '$.role')) = LOWER('admin') OR LOWER(json_extract(clusters, '$.role')) = LOWER('doctor')) AND json_array_length(clusters, '$.addresses') > 2)

// ==================== APPLICATION ELOQUENT ====================

$query = User::query();
$nestedGroup->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);
$users = $query->get();
// Utilisateurs actifs, admins ou docteurs, avec plus de 2 adresses
```

---

## Voir aussi

- `LogicalOperator` - Énumération des opérateurs logiques
- `ConditionNode` - Condition simple
- `FunctionNode` - Fonction SQL
- `Node` - Interface des nœuds
- `DatabaseDriver` - Énumération des drivers