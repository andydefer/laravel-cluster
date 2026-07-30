# GroupNode - Référence Technique

## Description

Groupe plusieurs nœuds de condition avec un opérateur logique (AND, OR, NOT) pour former des expressions complexes.

## Hiérarchie / Implémentations

```
Node (abstract)
    └── GroupNode
```

**Interfaces implémentées :**
- `NodeInterface`

## Rôle principal

`GroupNode` permet de combiner des conditions individuelles (`ConditionNode`, `SubConditionNode`, ou d'autres `GroupNode`) en utilisant des opérateurs logiques. Il constitue l'élément fondamental pour construire des arbres syntaxiques de requêtes complexes, supportant des opérations binaires (AND, OR) avec plusieurs enfants et des opérations unaires (NOT) avec un seul enfant.

---

## API / Méthodes publiques

### `__construct(LogicalOperator $operator, NodeInterface ...$children)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$operator` | `LogicalOperator` | Opérateur logique (AND, OR, NOT) |
| `$children` | `NodeInterface...` | Nœuds enfants à grouper (un pour NOT, plusieurs pour AND/OR) |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Groupe AND avec deux conditions
$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);

// Groupe NOT avec une condition
$notGroup = new GroupNode(
    LogicalOperator::NOT,
    new ConditionNode('age', ComparisonOperator::LESS_THAN, '18')
);
```

---

### `evaluate(ClusterVO $data): bool`

Évalue le groupe de conditions contre les données d'un cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `ClusterVO` | Données du cluster à évaluer |

**Retourne :** `bool` - `true` si le groupe est satisfait, `false` sinon

**Comportement spécifique :**
- **AND** : Tous les enfants doivent être `true`
- **OR** : Au moins un enfant doit être `true`
- **NOT** : L'enfant doit être `false`
- **Groupe vide** : `true` pour AND, `false` pour OR/NOT

**Exceptions :** Aucune

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);

$cluster = new ClusterVO([
    'status' => 'active',
    'role' => 'admin'
]);

$result = $group->evaluate($cluster); // true
```

---

### `toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

Génère une expression SQL pour le groupe de conditions.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | Nom de la colonne JSON |
| `$driver` | `DatabaseDriver` | Driver de base de données (MYSQL, PGSQL, SQLITE) |

**Retourne :** `string` - Expression SQL pour la condition

**Comportement spécifique :**
- **Groupe vide** : Retourne `'1=1'` pour AND, `'1=0'` pour OR/NOT
- **NOT** : Retourne `'NOT (condition)'`
- **AND/OR** : Combine les enfants avec `' AND '` ou `' OR '` et parenthèses

**Exceptions :** Aucune

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);

$sql = $group->toSql('clusters', DatabaseDriver::MYSQL);
// Résultat : "(LOWER(JSON_EXTRACT(clusters, '$.\"status\"')) = LOWER('active') AND LOWER(JSON_EXTRACT(clusters, '$.\"role\"')) = LOWER('admin'))"
```

---

### `toEloquent(Builder $query, string $column, DatabaseDriver $driver): void`

Applique le groupe de conditions à un constructeur de requête Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Constructeur de requête Eloquent |
| `$column` | `string` | Nom de la colonne JSON |
| `$driver` | `DatabaseDriver` | Driver de base de données |

**Retourne :** `void` (modifie `$query` par référence)

**Comportement :**
- **NOT** : Utilise `whereNot()` avec sous-requête
- **AND** : Ajoute des `where()` imbriqués
- **OR** : Utilise `orWhere()` avec sous-requête pour maintenir la priorité

**Exceptions :** Aucune

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;

$group = new GroupNode(
    LogicalOperator::OR,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);

$query = TestCluster::query();
$group->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

$results = $query->get(); // Tous les clusters actifs OU admins
```

---

### `getChildren(): array`

Retourne les nœuds enfants du groupe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, NodeInterface>` - Tableau des nœuds enfants

**Exceptions :** Aucune

**Exemple :**
```php
<?php

declare(strict_types=1);

$group = new GroupNode(
    LogicalOperator::AND,
    $condition1,
    $condition2,
    $condition3
);

$children = $group->getChildren();
// [ConditionNode, ConditionNode, ConditionNode]
```

---

## Cas d'utilisation

### Cas 1 : Filtrage avancé avec conditions multiples

Rechercher des clusters qui sont actifs ET administrateurs.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\ClusterQuery;

// Construire la requête : (status = 'active' AND role = 'admin')
$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);

$query = new ClusterQuery();
$collection = new ClusterVOCollection(/* ... */);

// Filtrer directement via l'évaluation
$filtered = $collection->filter(
    fn (ClusterVO $cluster) => $group->evaluate($cluster)
);
```

### Cas 2 : Requêtes combinées avec NOT et OR

Trouver les clusters qui ne sont pas mineurs OU qui sont administrateurs.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

// NOT (age < 18) OR role = 'admin'
$notMinor = new GroupNode(
    LogicalOperator::NOT,
    new ConditionNode('age', ComparisonOperator::LESS_THAN, '18')
);

$admin = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

$group = new GroupNode(
    LogicalOperator::OR,
    $notMinor,
    $admin
);

$query = TestCluster::query();
$group->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

$results = $query->get(); // Clusters non mineurs OU admins
```

### Cas 3 : Conditions imbriquées complexes

Combinaison de conditions avec plusieurs niveaux de priorité.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// (status = 'active' OR role = 'admin') AND verified = 'true'
$innerGroup = new GroupNode(
    LogicalOperator::OR,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);

$outerGroup = new GroupNode(
    LogicalOperator::AND,
    $innerGroup,
    new ConditionNode('verified', ComparisonOperator::EQUAL, 'true')
);

// Exécution : les conditions OR sont évaluées d'abord (priorité naturelle)
$cluster = new ClusterVO([
    'status' => 'inactive',
    'role' => 'admin',
    'verified' => 'true'
]);

$result = $outerGroup->evaluate($cluster); // true
```

### Cas 4 : Interrogation Eloquent avec groupement OR

Utiliser `toEloquent()` pour filtrer une base de données avec des conditions OR.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;

// Chercher les clusters où status = 'active' OU lang_fr = 'true'
$group = new GroupNode(
    LogicalOperator::OR,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'true')
);

$query = TestCluster::query()
    ->where('id', '>', 0)
    ->where('created_at', '>=', '2024-01-01');

$group->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

$results = $query->get();
// WHERE id > 0 
// AND created_at >= '2024-01-01' 
// AND (JSON_EXTRACT(...) = 'active' OR JSON_EXTRACT(...) = 'true')
```

---

## Gestion des erreurs

Le `GroupNode` ne lève pas d'exceptions directement. Les erreurs peuvent survenir lors de l'utilisation des méthodes `toSql()` ou `toEloquent()` si les nœuds enfants lèvent des exceptions.

| Situation | Exception | Message |
|-----------|-----------|---------|
| Groupe vide avec NOT | Aucune (évalue à false) | - |
| Groupe vide avec AND | Aucune (évalue à true) | - |
| Groupe vide en SQL | Aucune (`'1=1'` pour AND) | - |
| Opérateur non reconnu | Aucune (`default` non atteignable) | - |

**Note :** Les exceptions sont gérées par les nœuds enfants (`ConditionNode`, `SubConditionNode`) et remontées si nécessaire.

---

## Intégration

### Avec `ClusterQuery`
```php
<?php

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Nodes\GroupNode;

$clusterQuery = new ClusterQuery();
$ast = $clusterQuery->parse('(status=active AND role=admin)');
// $ast est un GroupNode

$result = $clusterQuery->filter($collection, '(status=active AND role=admin)');
// Le parser génère un GroupNode qui évalue la collection
```

### Avec `ConditionNode`
```php
<?php

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;

// Les ConditionNode sont les feuilles de l'arbre
$condition = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '18');
$group = new GroupNode(LogicalOperator::AND, $condition);
```

### Avec `SubConditionNode`
```php
<?php

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;

// Les SubConditionNode peuvent être enfants de GroupNode
$sub = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);
$group = new GroupNode(LogicalOperator::AND, $sub);
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `evaluate()` | **O(n)** | Parcourt les enfants séquentiellement, s'arrête tôt avec AND/OR (court-circuit) |
| `toSql()` | **O(n)** | Génère une string SQL en parcourant tous les enfants |
| `toEloquent()` | **O(n)** | Applique les conditions via le Query Builder, optimisé par MySQL/PostgreSQL |
| Mémoire | **O(n)** | Stocke les références des nœuds enfants |

**Optimisations :**
- **Court-circuit** : Les opérateurs AND/OR arrêtent l'évaluation dès que le résultat est déterminé
- **Pas de cache** : Chaque appel `evaluate()` recalcule tout (les données changent)
- **Construction flexible** : L'utilisation de variadiques `...$children` permet des groupements dynamiques

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet (types, énumérations) |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (utilise PHP 8+ uniquement) |

| Base de données | Support |
|-----------------|---------|
| MySQL 5.7+ | ✅ Complet |
| PostgreSQL 10+ | ✅ Complet |
| SQLite 3.10+ | ✅ Complet (avec `json_extract` et `json_each`) |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;

// 1. Construction d'une condition complexe
// (status = 'active' OR role = 'admin') AND (age >= 18 AND verified = 'true')
$innerOr = new GroupNode(
    LogicalOperator::OR,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);

$innerAnd = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('age', ComparisonOperator::GREATER_THAN_OR_EQUAL, '18'),
    new ConditionNode('verified', ComparisonOperator::EQUAL, 'true')
);

$finalGroup = new GroupNode(
    LogicalOperator::AND,
    $innerOr,
    $innerAnd
);

// 2. Évaluation sur une collection
$collection = new ClusterVOCollection([
    new ClusterVO([
        'status' => 'inactive',
        'role' => 'admin',
        'age' => '25',
        'verified' => 'true'
    ]), // true (admin + age >= 18 + verified)
    new ClusterVO([
        'status' => 'active',
        'role' => 'guest',
        'age' => '17',
        'verified' => 'true'
    ]), // false (age < 18)
    new ClusterVO([
        'status' => 'active',
        'role' => 'guest',
        'age' => '25',
        'verified' => 'false'
    ]), // false (verified = false)
]);

$filtered = $collection->filter(
    fn (ClusterVO $cluster) => $finalGroup->evaluate($cluster)
);

// Résultat : 1 cluster

// 3. Génération SQL
$sql = $finalGroup->toSql('clusters', DatabaseDriver::MYSQL);
echo $sql;
// ( (LOWER(JSON_EXTRACT(clusters, '$."status"')) = LOWER('active') 
//    OR LOWER(JSON_EXTRACT(clusters, '$."role"')) = LOWER('admin') ) 
//   AND 
//   ( CAST(JSON_EXTRACT(clusters, '$."age"') AS SIGNED) >= 18 
//     AND LOWER(JSON_EXTRACT(clusters, '$."verified"')) = LOWER('true') ) )

// 4. Application à Eloquent
$query = TestCluster::query();
$finalGroup->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

$results = $query->get();
echo "Nombre de résultats : " . $results->count();
```

---

## Voir aussi

- `ConditionNode` - Nœud de comparaison simple (feuille de l'arbre)
- `SubConditionNode` - Nœud de condition sur sous-objets (tableaux)
- `ClusterQuery` - Service principal pour l'évaluation des requêtes
- `LogicalOperator` - Énumération des opérateurs logiques (AND, OR, NOT)
- `NodeInterface` - Interface commune à tous les nœuds de l'AST