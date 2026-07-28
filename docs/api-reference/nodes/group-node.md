# GroupNode - Référence Technique

## Description

Nœud composite qui regroupe plusieurs nœuds enfants avec un opérateur logique. Il permet de construire des expressions logiques complexes en combinant des conditions atomiques (`ConditionNode`) et d'autres groupes (`GroupNode`).

## Hiérarchie

```
Node
    └── GroupNode
```

**Interfaces :** `NodeInterface` (via `Node`)

## Rôle principal

`GroupNode` est le nœud logique de l'arbre syntaxique. Il représente des expressions composées comme :

- `(age > 18 AND status = 'active')`
- `(role = 'admin' OR role = 'manager')`
- `NOT (deleted_at IS NOT NULL)`

Il gère :
- L'évaluation logique des conditions regroupées (`AND`, `OR`, `NOT`)
- La génération de SQL pour les opérateurs logiques
- L'application à des requêtes Eloquent avec gestion des sous-requêtes
- L'imbrication de groupes pour des expressions complexes

---

## API / Méthodes publiques

### `__construct(LogicalOperator $operator, NodeInterface ...$children)`

Initialise un nœud de groupe avec un opérateur logique et des nœuds enfants.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$operator` | `LogicalOperator` | Opérateur logique (`AND`, `OR`, `NOT`) |
| `...$children` | `NodeInterface` | Nœuds enfants (un pour `NOT`, plusieurs pour `AND`/`OR`) |

**Exemple :**
```php
// Groupe AND avec 2 conditions
$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('age', ComparisonOperator::GREATER_THAN, '18'),
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active')
);

// Groupe NOT avec 1 condition
$notGroup = new GroupNode(
    LogicalOperator::NOT,
    new ConditionNode('deleted_at', ComparisonOperator::EXISTS)
);
```

---

### `evaluate(ClusterVO $data): bool`

Évalue le groupe de conditions sur un objet `ClusterVO`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `ClusterVO` | Objet contenant les données à évaluer |

**Retourne :** `bool` - `true` si l'ensemble des conditions est satisfait, `false` sinon

**Comportement :**
- Groupe vide → retourne `true` pour `AND`, `false` pour `OR`/`NOT`
- `NOT` → négation logique du premier enfant
- `AND` → évaluation séquentielle avec court-circuit
- `OR` → évaluation séquentielle avec court-circuit

**Exemple :**
```php
$data = new ClusterVO(['age' => 25, 'status' => 'active']);
$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('age', ComparisonOperator::GREATER_THAN, '18'),
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active')
);

if ($group->evaluate($data)) {
    echo "Conditions remplies";
}
```

---

### `toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

Génère la requête SQL pour le groupe logique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | Nom de la colonne JSON à interroger |
| `$driver` | `DatabaseDriver` | Moteur de base de données cible |

**Retourne :** `string` - Fragment SQL représentant le groupe logique

**Comportement :**
- Groupe vide → `'1=1'` (toujours vrai)
- `NOT` → `'NOT (' + SQL enfant + ')'`
- `AND`/`OR` → `'(' + SQL_enfant1 + ' AND ' + SQL_enfant2 + ... + ')'`

**Exemple :**
```php
$sql = $group->toSql('data', DatabaseDriver::MYSQL);
// Résultat : "(JSON_EXTRACT(data, '$."age"') > '18' AND JSON_EXTRACT(data, '$."status"') = 'active')"
```

---

### `toEloquent(Builder $query, string $column, DatabaseDriver $driver): void`

Applique le groupe logique à une requête Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Instance du constructeur de requête Eloquent |
| `$column` | `string` | Nom de la colonne JSON |
| `$driver` | `DatabaseDriver` | Moteur de base de données |

**Comportement :**
- Utilise `where()` avec une sous-requête pour le groupe principal
- `AND` → `where()` successifs à l'intérieur de la sous-requête
- `OR` → `orWhere()` avec des sous-requêtes imbriquées

**Exemple :**
```php
$query = User::query();
$group->toEloquent($query, 'metadata', DatabaseDriver::MYSQL);
$users = $query->get();
// SELECT * FROM users WHERE (JSON_EXTRACT(metadata, '$."age"') > '18' AND JSON_EXTRACT(metadata, '$."status"') = 'active')
```

---

### `getChildren(): array`

Retourne les nœuds enfants du groupe.

**Retourne :** `array<int, NodeInterface>` - Tableau des nœuds enfants

**Exemple :**
```php
$children = $group->getChildren();
foreach ($children as $child) {
    echo get_class($child);
}
```

---

### Méthodes privées

| Méthode | Rôle |
|---------|------|
| `applyBinaryOperation()` | Applique une opération binaire à Eloquent |
| `applySubsequentCondition()` | Applique les conditions suivantes à Eloquent |

---

## Cas d'utilisation

### Cas 1 : Condition AND simple

Trouver les utilisateurs actifs majeurs.

```php
<?php

use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('age', ComparisonOperator::GREATER_THAN_OR_EQUAL, '18'),
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active')
);

$query = User::query();
$group->toEloquent($query, 'metadata', DatabaseDriver::MYSQL);
$adults = $query->get();
```

---

### Cas 2 : Condition OR pour plusieurs rôles

Trouver les administrateurs ou les managers.

```php
<?php

$group = new GroupNode(
    LogicalOperator::OR,
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'manager'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'supervisor')
);

// SQL généré : (role = 'admin' OR role = 'manager' OR role = 'supervisor')
```

---

### Cas 3 : Négation avec NOT

Trouver les utilisateurs non supprimés.

```php
<?php

$notGroup = new GroupNode(
    LogicalOperator::NOT,
    new ConditionNode('deleted_at', ComparisonOperator::EXISTS)
);

// SQL généré : NOT (deleted_at IS NOT NULL)
// Équivaut à : deleted_at IS NULL
```

---

### Cas 4 : Groupes imbriqués

Trouver les administrateurs actifs OU les utilisateurs vérifiés majeurs.

```php
<?php

$group = new GroupNode(
    LogicalOperator::OR,
    new GroupNode(
        LogicalOperator::AND,
        new ConditionNode('role', ComparisonOperator::EQUAL, 'admin'),
        new ConditionNode('status', ComparisonOperator::EQUAL, 'active')
    ),
    new GroupNode(
        LogicalOperator::AND,
        new ConditionNode('verified', ComparisonOperator::EQUAL, 'true'),
        new ConditionNode('age', ComparisonOperator::GREATER_THAN_OR_EQUAL, '18')
    )
);

// SQL : 
// ((role = 'admin' AND status = 'active') OR (verified = 'true' AND age >= 18))
```

---

### Cas 5 : Évaluation en mémoire

Filtrer des données en mémoire avant export.

```php
<?php

$data = [
    new ClusterVO(['age' => 25, 'status' => 'active', 'role' => 'admin']),
    new ClusterVO(['age' => 17, 'status' => 'active', 'role' => 'user']),
    new ClusterVO(['age' => 30, 'status' => 'inactive', 'role' => 'admin']),
];

$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin'),
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active')
);

$filtered = array_filter($data, fn($item) => $group->evaluate($item));
// Résultat : seul le premier cluster (25, active, admin)
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Groupe vide pour NOT | Aucune (premier enfant manquant) | `Undefined array key 0` (⚠️ comportement non sécurisé) |
| Enfant non `NodeInterface` | `TypeError` | Dépend du type de l'objet |

⚠️ **Amélioration recommandée** : La méthode `evaluate()` pour `NOT` devrait vérifier que `$this->children[0]` existe.

```php
if ($this->operator === LogicalOperator::NOT) {
    if (empty($this->children)) {
        throw new \InvalidArgumentException('NOT operator requires exactly one child');
    }
    return !$this->children[0]->evaluate($data);
}
```

---

## Intégration

`GroupNode` s'intègre avec :

- **`Node`** : Classe parente abstraite
- **`NodeInterface`** : Interface de base
- **`LogicalOperator`** : Énumération des opérateurs logiques
- **`ConditionNode`** : Nœuds conditionnels enfants
- **`GroupNode`** : Auto-intégration (groupes imbriqués)
- **`DatabaseDriver`** : Énumération des moteurs de bases de données
- **Eloquent `Builder`** : Construction de requêtes

---

## Performance

### Complexité algorithmique

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `evaluate()` - AND | O(n) | Court-circuit possible |
| `evaluate()` - OR | O(n) | Court-circuit possible |
| `evaluate()` - NOT | O(1) | Un seul enfant |
| `toSql()` | O(n) | Traverse tous les enfants |
| `toEloquent()` | O(n) | Traverse tous les enfants |

### Optimisations

- Court-circuit pour `AND` (dès qu'une condition est fausse) et `OR` (dès qu'une condition est vraie)
- Construction de SQL directe sans parsing supplémentaire
- Sous-requêtes Eloquent pour une gestion optimisée des `OR`

### Considérations mémoire

- La profondeur d'imbrication peut impacter la mémoire et la performance
- Pour des expressions très complexes (> 100 nœuds), considérer une compilation en SQL direct

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (nécessite PHP 8.0+) |

**Moteurs de bases de données supportés :**

| Base de données | Support SQL | Support Eloquent |
|-----------------|-------------|------------------|
| MySQL | ✅ Complet | ✅ Complet |
| PostgreSQL | ✅ Complet | ✅ Complet |
| SQLite | ✅ Complet | ✅ Complet |

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
use App\Models\User;

// 1. Expression complexe : administrateurs actifs OU utilisateurs vérifiés majeurs
$group = new GroupNode(
    LogicalOperator::OR,
    new GroupNode(
        LogicalOperator::AND,
        new ConditionNode('role', ComparisonOperator::EQUAL, 'admin'),
        new ConditionNode('status', ComparisonOperator::EQUAL, 'active')
    ),
    new GroupNode(
        LogicalOperator::AND,
        new ConditionNode('verified', ComparisonOperator::EQUAL, 'true'),
        new ConditionNode('age', ComparisonOperator::GREATER_THAN_OR_EQUAL, '18')
    )
);

// 2. Évaluation en mémoire
$data = new ClusterVO([
    'role' => 'admin',
    'status' => 'active',
    'verified' => 'false',
    'age' => 30
]);

echo $group->evaluate($data) ? 'Match' : 'No match';
// "Match" (admin ET active)

// 3. Génération SQL MySQL
$sql = $group->toSql('data', DatabaseDriver::MYSQL);
echo $sql . PHP_EOL;
// Résultat :
// ((JSON_EXTRACT(data, '$."role"') = 'admin' AND JSON_EXTRACT(data, '$."status"') = 'active')
// OR (JSON_EXTRACT(data, '$."verified"') = 'true' AND CAST(JSON_EXTRACT(data, '$."age"') AS DECIMAL(10,2)) >= '18'))

// 4. Application à Eloquent
$query = User::query();
$group->toEloquent($query, 'metadata', DatabaseDriver::MYSQL);
$users = $query->get();
echo "Nombre d'utilisateurs : " . $users->count() . PHP_EOL;

// 5. Groupe AND simple
$andGroup = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('deleted_at', ComparisonOperator::NOT_EXISTS)
);

// 6. Groupe NOT
$notGroup = new GroupNode(
    LogicalOperator::NOT,
    new ConditionNode('suspended', ComparisonOperator::EQUAL, 'true')
);
// Équivaut à : suspended != 'true'

// 7. Groupe vide (toujours vrai)
$emptyGroup = new GroupNode(LogicalOperator::AND);
echo $emptyGroup->toSql('data');
// "1=1"

// 8. Utilisation avec un arbre complexe
$complexTree = new GroupNode(
    LogicalOperator::AND,
    new GroupNode(
        LogicalOperator::OR,
        new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
        new ConditionNode('status', ComparisonOperator::EQUAL, 'pending')
    ),
    new GroupNode(
        LogicalOperator::NOT,
        new ConditionNode('deleted_at', ComparisonOperator::EXISTS)
    )
);

// SQL : ((status = 'active' OR status = 'pending') AND NOT (deleted_at IS NOT NULL))
```

---

## Voir aussi

- `Node` - Classe parente abstraite
- `ConditionNode` - Nœud conditionnel atomique
- `LogicalOperator` - Énumération des opérateurs logiques
- `NodeInterface` - Interface de base des nœuds
- `TokenRecordCollection` - Collection de tokens pour l'analyse syntaxique