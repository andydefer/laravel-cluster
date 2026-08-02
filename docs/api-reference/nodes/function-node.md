# FunctionNode - Référence Technique

## Description

Nœud de l'AST représentant une fonction SQL appliquée à un chemin JSON. Gère les fonctions d'agrégation (COUNT, SUM, AVG, MIN, MAX) et les fonctions JSON (JSON_LENGTH, CONTAINS).

## Hiérarchie / Implémentations

```
Node (classe abstraite)
    └── FunctionNode (classe finale)
```

## Rôle principal

Le `FunctionNode` est le composant qui exécute et génère le SQL pour les fonctions SQL dans Laravel Cluster. Il :

- **Évalue** les fonctions en mémoire sur des données `ClusterVO`
- **Génère** le SQL adapté à chaque driver (SQLite, MySQL, PostgreSQL)
- **Applique** les fonctions aux requêtes Eloquent
- **Gère** les différents types de fonctions (agrégation, JSON, booléennes)

## API / Méthodes publiques

### `__construct(string $functionName, string $path, ComparisonOperator $operator, ?string $value = null, array $args = [])`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$functionName` | `string` | Nom de la fonction (COUNT, SUM, AVG, MIN, MAX, LENGTH, JSON_LENGTH, CONTAINS) |
| `$path` | `string` | Chemin JSON (ex: 'addresses', 'profile.languages') |
| `$operator` | `ComparisonOperator` | Opérateur de comparaison |
| `$value` | `string|null` | Valeur de comparaison (ex: '2', 'true') |
| `$args` | `array` | Arguments supplémentaires (ex: ['languages', 'fr'] pour CONTAINS) |

**Exemple :**
```php
use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// COUNT avec opérateur >
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');

// CONTAINS avec opérateur =
$node = new FunctionNode('CONTAINS', 'languages', ComparisonOperator::EQUAL, 'true', ['languages', 'fr']);
```

---

### `evaluate(ClusterVO $cluster): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Les données du cluster à évaluer |

**Retourne :** `bool` - `true` si la condition est satisfaite

**Exemple :**
```php
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');
$cluster = new ClusterVO(['addresses' => ['a', 'b', 'c']]);
$result = $node->evaluate($cluster); // true
```

---

### `toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne JSON contenant les données |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string` - L'expression SQL

**Exceptions :** `InvalidArgumentException` - Si l'opérateur n'est pas supporté

**Exemple :**
```php
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');

// MySQL
$sql = $node->toSql('clusters', DatabaseDriver::MYSQL);
// "JSON_LENGTH(clusters, '$.addresses') > 2"

// SQLite
$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// "json_array_length(clusters, '$.addresses') > 2"

// PostgreSQL
$sql = $node->toSql('clusters', DatabaseDriver::PGSQL);
// "jsonb_array_length(clusters->'addresses') > 2"
```

---

### `toEloquent(Builder $query, string $column, DatabaseDriver $driver): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Le query builder Eloquent |
| `$column` | `string` | La colonne JSON contenant les données |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Exemple :**
```php
use App\Models\User;

$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');
$query = User::query();
$node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
$users = $query->get();
// SELECT * FROM users WHERE JSON_LENGTH(clusters, '$.addresses') > 2
```

---

### `getChildren(): array`

**Retourne :** `array` - Tableau vide (nœud feuille)

**Exemple :**
```php
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');
$children = $node->getChildren(); // []
```

## Cas d'utilisation

### Cas 1 : Fonction COUNT avec Eloquent
```php
use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');
$query = User::query();
$node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
$users = $query->get();
// Utilisateurs avec plus de 2 adresses
```

### Cas 2 : Fonction CONTAINS avec ClusterVO
```php
use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$node = new FunctionNode('CONTAINS', 'languages', ComparisonOperator::EQUAL, 'true', ['languages', 'fr']);
$cluster = new ClusterVO(['languages' => ['fr', 'en', 'es']]);
$result = $node->evaluate($cluster); // true
```

### Cas 3 : Fonction AVG avec SQL
```php
$node = new FunctionNode('AVG', 'scores', ComparisonOperator::GREATER_THAN_OR_EQUAL, '85');

// MySQL
$sql = $node->toSql('clusters', DatabaseDriver::MYSQL);
// "AVG(CAST(JSON_EXTRACT(clusters, '$.scores') AS DECIMAL(10,2))) >= 85"

// SQLite
$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// "AVG(CAST(json_extract(clusters, '$.scores') AS NUMERIC)) >= 85"
```

### Cas 4 : Fonction SUM avec opérateur EXISTS
```php
$node = new FunctionNode('SUM', 'prices', ComparisonOperator::EXISTS);
$cluster = new ClusterVO(['prices' => [100, 200, 300]]);
$result = $node->evaluate($cluster); // true (la somme existe)
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Opérateur non supporté | `InvalidArgumentException` | `Unsupported operator for SQL function` |
| Fonction non enregistrée | - | Retourne `'1=0'` dans `toSql()` |
| Fonction non enregistrée | - | Retourne `false` dans `evaluate()` |
| Chemin inexistant | - | Retourne `false` dans `evaluate()` |

## Intégration

Le `FunctionNode` interagit avec :

- **`SqlFunctionRegistry`** : Pour obtenir la génération SQL et l'exécution des fonctions
- **`ClusterVO`** : Pour l'extraction des données en mémoire
- **`Eloquent Builder`** : Pour l'application des conditions SQL
- **`ComparisonOperator`** : Pour évaluer les comparaisons

### Cycle de vie d'un FunctionNode

```
1. Construction avec fonction, chemin, opérateur, valeur et arguments
   ↓
2. Évaluation en mémoire : evaluate(ClusterVO)
   ↓
   - Extrait la valeur du chemin
   - Exécute la fonction via le registre
   - Compare le résultat avec la valeur via l'opérateur
   ↓
3. Génération SQL : toSql(column, driver)
   ↓
   - Récupère l'expression SQL via le registre
   - Applique l'opérateur et la valeur de comparaison
   ↓
4. Application Eloquent : toEloquent(query, column, driver)
   ↓
   - Ajoute la condition au query builder
```

## Performance

- **Extraction** : O(n) où n est la profondeur du chemin
- **Évaluation** : O(1) via le registre
- **Génération SQL** : O(1) via le registre

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use App\Models\User;

// 1. Création d'un FunctionNode COUNT
$node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');

// 2. Évaluation en mémoire
$cluster = new ClusterVO(['addresses' => ['a', 'b', 'c']]);
$result = $node->evaluate($cluster); // true

// 3. Génération SQL MySQL
$sql = $node->toSql('clusters', DatabaseDriver::MYSQL);
// "JSON_LENGTH(clusters, '$.addresses') > 2"

// 4. Génération SQL SQLite
$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// "json_array_length(clusters, '$.addresses') > 2"

// 5. Application Eloquent
$query = User::query();
$node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
$users = $query->get();

// 6. FunctionNode CONTAINS
$node = new FunctionNode('CONTAINS', 'languages', ComparisonOperator::EQUAL, 'true', ['languages', 'fr']);
$cluster = new ClusterVO(['languages' => ['fr', 'en']]);
$result = $node->evaluate($cluster); // true

// 7. SQL pour CONTAINS
$sql = $node->toSql('clusters', DatabaseDriver::SQLITE);
// "EXISTS (SELECT 1 FROM json_each(clusters, '$.languages') WHERE value = 'fr')"
```

## Voir aussi

- [`Node`](Node.md) - Classe de base des nœuds AST
- [`ConditionNode`](ConditionNode.md) - Nœud de condition simple
- [`GroupNode`](GroupNode.md) - Nœud de groupe logique (AND/OR)
- [`SubConditionNode`](SubConditionNode.md) - Nœud de sous-condition
- [`SqlFunctionRegistry`](../Registry/SqlFunctionRegistry.md) - Registre des fonctions SQL
- [`ComparisonOperator`](../Enums/ComparisonOperator.md) - Opérateurs de comparaison
- [`ClusterVO`](../ValueObjects/ClusterVO.md) - Conteneur de données