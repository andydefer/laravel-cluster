# ConditionNode - Technical Reference

## Description

Représente un nœud de condition simple dans l'arbre syntaxique abstrait (AST). Il gère les opérations de comparaison entre un chemin JSON et une valeur, avec un support multi-drivers (SQLite, MySQL, PostgreSQL).

## Hiérarchie

```
Node
    └── ConditionNode
```

## Rôle principal

Évalue une condition de comparaison sur un cluster et génère le SQL correspondant pour différents drivers de base de données. Il supporte :

- **Comparaison simple** : `=`, `!=`, `>`, `<`, `>=`, `<=`
- **Comparaison stricte** : `===`, `!==`
- **Opérateurs spéciaux** : `EXISTS`, `NOT_EXISTS`
- **Recherche textuelle** : `LIKE`, `NOT_LIKE`
- **Comparaison spaceship** : `<=>`

---

## API

### `__construct(string $key, ComparisonOperator $operator, ?string $value = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé JSON à vérifier |
| `$operator` | `ComparisonOperator` | L'opérateur de comparaison |
| `$value` | `string|null` | La valeur de comparaison (optionnelle) |

**Exemple :**
```php
$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
```

---

### `getOperator(): ComparisonOperator`

Retourne l'opérateur de comparaison.

**Retourne :** `ComparisonOperator` - L'opérateur

---

### `getKey(): string`

Retourne la clé JSON.

**Retourne :** `string` - La clé

---

### `getValue(): ?string`

Retourne la valeur de comparaison.

**Retourne :** `string|null` - La valeur

---

### `isEmptyCondition(): bool`

Détermine si la condition est vide (utilisée pour les sous-conditions).

**Retourne :** `bool` - `true` si la condition est `__empty__` avec `EQUAL`

**Exemple :**
```php
$node = new ConditionNode('__empty__', ComparisonOperator::EQUAL, 'yes');
$node->isEmptyCondition(); // true
```

---

### `isWildcardExists(): bool`

Détermine si la condition est un wildcard EXISTS.

**Retourne :** `bool` - `true` si la clé est `*` avec `EXISTS`

**Exemple :**
```php
$node = new ConditionNode('*', ComparisonOperator::EXISTS);
$node->isWildcardExists(); // true
```

---

### `evaluate(ClusterVO $data): bool`

Évalue la condition contre un cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `ClusterVO` | Le cluster à évaluer |

**Retourne :** `bool` - `true` si la condition est satisfaite

**Exemple :**
```php
$cluster = new ClusterVO(['status' => 'active']);
$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$result = $node->evaluate($cluster); // true
```

---

### `toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

Génère l'expression SQL pour la condition.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne JSON en base de données |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

**Exemple :**
```php
$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// LOWER(json_extract(clusters, '$.status')) = LOWER('active')
```

---

### `toEloquent(Builder $query, string $column, DatabaseDriver $driver): void`

Applique la condition à un builder Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Le builder Eloquent |
| `$column` | `string` | La colonne JSON |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Exemple :**
```php
$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$query = User::query();
$node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);
$users = $query->get();
```

---

### `getChildren(): array`

Retourne les nœuds enfants (vide pour les feuilles).

**Retourne :** `array` - Un tableau vide

---

## Gestion des clés manquantes

| Opérateur | Clé existante | Clé manquante |
|-----------|---------------|---------------|
| `EXISTS` | `true` | `false` |
| `NOT_EXISTS` | `false` | `true` |
| `EQUAL` avec `'no'`, `'false'` ou `'null'` | Dépend de la valeur | `true` |
| `EQUAL` avec `'yes'` ou `'true'` | Dépend de la valeur | `false` |
| Autres opérateurs | Dépend de la valeur | `false` |

---

## Opérateurs supportés

| Opérateur | SQL généré | Description |
|-----------|------------|-------------|
| `EQUAL` | `= LOWER(value)` | Égalité insensible à la casse |
| `EQUAL_LOOSE` | `= LOWER(value)` | Égalité insensible à la casse |
| `EQUAL_STRICT` | `= value` | Égalité sensible à la casse |
| `NOT_EQUAL` | `!= LOWER(value)` | Différent insensible à la casse |
| `NOT_EQUAL_STRICT` | `!= value` | Différent sensible à la casse |
| `GREATER_THAN` | `> value` | Supérieur |
| `GREATER_THAN_OR_EQUAL` | `>= value` | Supérieur ou égal |
| `LESS_THAN` | `< value` | Inférieur |
| `LESS_THAN_OR_EQUAL` | `<= value` | Inférieur ou égal |
| `SPACESHIP` | `<=> value` | Comparaison |
| `LIKE` | `LIKE pattern` | Recherche insensible à la casse |
| `NOT_LIKE` | `NOT LIKE pattern` | Exclusion insensible à la casse |
| `EXISTS` | `IS NOT NULL` | La clé existe |
| `NOT_EXISTS` | `IS NULL` | La clé n'existe pas |

---

## Cas d'utilisation

### Cas 1 : Condition simple avec valeur booléenne

```php
$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');

// Évaluation
$cluster = new ClusterVO(['status' => 'active']);
$result = $node->evaluate($cluster); // true

// SQL
$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// LOWER(json_extract(clusters, '$.status')) = LOWER('active')
```

### Cas 2 : Condition avec 'yes'/'no'

```php
// Vérification d'une valeur 'yes'
$node = new ConditionNode('verified', ComparisonOperator::EQUAL, 'yes');

$cluster1 = new ClusterVO(['verified' => 'yes']);
$cluster2 = new ClusterVO(['verified' => 'no']);
$cluster3 = new ClusterVO(['status' => 'active']); // clé manquante

var_dump($node->evaluate($cluster1)); // true
var_dump($node->evaluate($cluster2)); // false
var_dump($node->evaluate($cluster3)); // false (clé manquante, 'yes' n'est pas une valeur par défaut)

// Vérification d'une valeur 'no' (inclut les clés manquantes)
$node = new ConditionNode('verified', ComparisonOperator::EQUAL, 'no');

var_dump($node->evaluate($cluster1)); // false
var_dump($node->evaluate($cluster2)); // true
var_dump($node->evaluate($cluster3)); // true (clé manquante équivaut à 'no')
```

### Cas 3 : Comparaison numérique

```php
$node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '18');

$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// CAST(json_extract(clusters, '$.age') AS NUMERIC) > 18

$sql = $node->toSql('clusters', DatabaseDriver::MYSQL);
// CAST(JSON_EXTRACT(clusters, '$."age"') AS DECIMAL(10,2)) > 18
```

### Cas 4 : LIKE pattern

```php
$node = new ConditionNode('name', ComparisonOperator::LIKE, 'John%');

$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// LOWER(json_extract(clusters, '$.name')) LIKE LOWER('John%')
```

### Cas 5 : EXISTS / NOT_EXISTS

```php
// Existence d'une clé
$node = new ConditionNode('email', ComparisonOperator::EXISTS);
$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// json_extract(clusters, '$.email') IS NOT NULL

// Absence d'une clé
$node = new ConditionNode('email', ComparisonOperator::NOT_EXISTS);
$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// json_extract(clusters, '$.email') IS NULL
```

### Cas 6 : Application Eloquent avec valeurs booléennes

```php
$query = User::query();

// Utilisateurs vérifiés
$node = new ConditionNode('verified', ComparisonOperator::EQUAL, 'yes');
$node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

// Utilisateurs non vérifiés (inclut ceux sans la clé)
$node = new ConditionNode('verified', ComparisonOperator::EQUAL, 'no');
$node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

// Chaînage
$node2 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$node2->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

$users = $query->get();
// Utilisateurs avec verified='yes' ET status='active'
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Clé invalide | `InvalidArgumentException` | `Invalid JSON key: {key}` |
| Opérateur non supporté | `InvalidArgumentException` | `Unsupported operator: {operator}` |

---

## Performance

- **Évaluation :** O(1) - Accès direct aux données aplaties
- **SQL :** Génération à la volée, pas de cache
- **Eloquent :** Ajout de conditions `whereRaw`, pas d'impact significatif

---

## Compatibilité

| Driver | JSON Extraction | LIKE | Numeric |
|--------|-----------------|------|---------|
| SQLite | `json_extract` | `LOWER(...) LIKE` | `CAST(... AS NUMERIC)` |
| MySQL | `JSON_EXTRACT` | `LOWER(...) LIKE` | `CAST(... AS DECIMAL(10,2))` |
| PostgreSQL | `->>` | `LOWER(...) LIKE` | `::numeric` |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

// ==================== CRÉATION ====================

$statusNode = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$ageNode = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '18');
$nameNode = new ConditionNode('name', ComparisonOperator::LIKE, 'John%');
$existsNode = new ConditionNode('email', ComparisonOperator::EXISTS);
$notExistsNode = new ConditionNode('email', ComparisonOperator::NOT_EXISTS);
$verifiedNode = new ConditionNode('verified', ComparisonOperator::EQUAL, 'yes');
$unverifiedNode = new ConditionNode('verified', ComparisonOperator::EQUAL, 'no');

// ==================== ÉVALUATION ====================

$cluster = new ClusterVO([
    'status' => 'active',
    'age' => 25,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'verified' => 'yes',
]);

var_dump($statusNode->evaluate($cluster)); // true
var_dump($ageNode->evaluate($cluster)); // true
var_dump($nameNode->evaluate($cluster)); // true
var_dump($existsNode->evaluate($cluster)); // true
var_dump($notExistsNode->evaluate($cluster)); // false
var_dump($verifiedNode->evaluate($cluster)); // true
var_dump($unverifiedNode->evaluate($cluster)); // false

// Cluster sans la clé 'verified'
$cluster2 = new ClusterVO([
    'status' => 'active',
    'age' => 25,
    'name' => 'John Doe',
]);

var_dump($verifiedNode->evaluate($cluster2)); // false
var_dump($unverifiedNode->evaluate($cluster2)); // true (clé manquante = 'no')

// ==================== GÉNÉRATION SQL ====================

$column = 'clusters';

echo "SQLite:\n";
echo $statusNode->toSql($column, DatabaseDriver::SQLITE) . "\n";
// LOWER(json_extract(clusters, '$.status')) = LOWER('active')

echo $ageNode->toSql($column, DatabaseDriver::SQLITE) . "\n";
// CAST(json_extract(clusters, '$.age') AS NUMERIC) > 18

echo $verifiedNode->toSql($column, DatabaseDriver::SQLITE) . "\n";
// LOWER(json_extract(clusters, '$.verified')) = LOWER('yes')

echo "MySQL:\n";
echo $statusNode->toSql($column, DatabaseDriver::MYSQL) . "\n";
// LOWER(JSON_EXTRACT(clusters, '$."status"')) = LOWER('active')

echo "PostgreSQL:\n";
echo $statusNode->toSql($column, DatabaseDriver::PGSQL) . "\n";
// LOWER(clusters->>'status') = LOWER('active')

// ==================== APPLICATION ELOQUENT ====================

$query = User::query();

// Filtrage avec valeurs booléennes
$statusNode->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);
$verifiedNode->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);
$ageNode->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

$users = $query->get();
// Utilisateurs avec status='active', verified='yes', et age>18
```

---

## Voir aussi

- `ComparisonOperator` - Énumération des opérateurs
- `DatabaseDriver` - Énumération des drivers
- `Node` - Classe parente
- `GroupNode` - Groupe de conditions
- `SubConditionNode` - Sous-condition
- `FunctionNode` - Fonction SQL
