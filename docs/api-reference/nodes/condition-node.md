# ConditionNode - Référence Technique

## Description

Représente une condition de comparaison atomique dans une requête, comparant une clé JSON à une valeur via un opérateur de comparaison.

## Hiérarchie / Implémentations

```
Node (abstract)
    └── ConditionNode
```

**Interfaces implémentées :**
- `NodeInterface`

## Rôle principal

`ConditionNode` constitue la feuille de l'arbre syntaxique des requêtes. Il évalue une condition simple comme `status = 'active'` ou `age > 18` en comparant une clé d'un objet JSON à une valeur donnée. Il supporte les opérateurs de comparaison, d'existence, et les motifs LIKE pour les recherches textuelles.

---

## API / Méthodes publiques

### `__construct(string $key, ComparisonOperator $operator, ?string $value = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé JSON à évaluer |
| `$operator` | `ComparisonOperator` | Opérateur de comparaison |
| `$value` | `?string` | Valeur de comparaison (null pour EXISTS/NOT_EXISTS) |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Égalité simple
$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');

// Existence d'une clé
$node = new ConditionNode('email', ComparisonOperator::EXISTS);

// Recherche textuelle
$node = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');
```

---

### `getOperator(): ComparisonOperator`

Retourne l'opérateur de comparaison du nœud.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `ComparisonOperator` - L'opérateur de comparaison

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$operator = $node->getOperator(); // ComparisonOperator::EQUAL
```

---

### `isEmptyCondition(): bool`

Vérifie si la condition est une condition factice `__empty__` utilisée pour tester si un tableau est vide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - `true` si c'est une condition `__empty__`, `false` sinon

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$node = new ConditionNode('__empty__', ComparisonOperator::EQUAL);
$isEmpty = $node->isEmptyCondition(); // true
```

---

### `isWildcardExists(): bool`

Vérifie si la condition est un `EXISTS` sur un wildcard (`*`) utilisé pour tester si un tableau contient des éléments.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - `true` si c'est une condition wildcard EXISTS, `false` sinon

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$node = new ConditionNode('*', ComparisonOperator::EXISTS);
$isWildcard = $node->isWildcardExists(); // true
```

---

### `evaluate(ClusterVO $data): bool`

Évalue la condition contre les données d'un cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `ClusterVO` | Données du cluster à évaluer |

**Retourne :** `bool` - `true` si la condition est satisfaite

**Comportement spécifique :**
- **EXISTS** : Vérifie si la clé existe dans les données
- **NOT_EXISTS** : Vérifie si la clé n'existe pas
- **Clé manquante** : Retourne `false` (sauf pour EQUAL avec 'false' ou 'null')
- **Autres opérateurs** : Compare la valeur via l'opérateur

**Exceptions :** Aucune

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$cluster = new ClusterVO(['status' => 'active']);

$result = $node->evaluate($cluster); // true
```

---

### `toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

Génère une expression SQL pour la condition.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | Nom de la colonne JSON |
| `$driver` | `DatabaseDriver` | Driver de base de données (MYSQL, PGSQL, SQLITE) |

**Retourne :** `string` - Expression SQL pour la condition

**Exceptions :** 
- `InvalidArgumentException` si l'opérateur n'est pas supporté
- `InvalidArgumentException` si la clé JSON est invalide

**Exemple :**
```php
<?php

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$sql = $node->toSql('clusters', DatabaseDriver::MYSQL);
// "LOWER(JSON_EXTRACT(clusters, '$.\"status\"')) = LOWER('active')"
```

---

### `toEloquent(Builder $query, string $column, DatabaseDriver $driver): void`

Applique la condition à un constructeur de requête Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Constructeur de requête Eloquent |
| `$column` | `string` | Nom de la colonne JSON |
| `$driver` | `DatabaseDriver` | Driver de base de données |

**Retourne :** `void` (modifie `$query` par référence)

**Exceptions :**
- `InvalidArgumentException` si l'opérateur n'est pas supporté
- `InvalidArgumentException` si la clé JSON est invalide

**Exemple :**
```php
<?php

use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$query = TestCluster::query();

$node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
$results = $query->get(); // Clusters avec status = 'active'
```

---

### `getChildren(): array`

Retourne les nœuds enfants (toujours vide pour une condition atomique).

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, NodeInterface>` - Tableau vide

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$children = $node->getChildren(); // []
```

---

## Cas d'utilisation

### Cas 1 : Filtrage simple par égalité

Rechercher les clusters ayant un statut spécifique.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$activeNodes = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');

$collection = new ClusterVOCollection([
    new ClusterVO(['status' => 'active', 'name' => 'Cluster A']),
    new ClusterVO(['status' => 'inactive', 'name' => 'Cluster B']),
    new ClusterVO(['status' => 'active', 'name' => 'Cluster C']),
]);

$filtered = $collection->filter(
    fn (ClusterVO $cluster) => $activeNodes->evaluate($cluster)
);
// Résultat : 2 clusters (A et C)
```

### Cas 2 : Vérification d'existence de clé

Trouver les clusters qui ont un champ optionnel défini.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Clusters ayant une adresse email définie
$hasEmail = new ConditionNode('email', ComparisonOperator::EXISTS);

$collection->filter(fn ($cluster) => $hasEmail->evaluate($cluster));

// Clusters n'ayant pas d'email
$noEmail = new ConditionNode('email', ComparisonOperator::NOT_EXISTS);
$collection->filter(fn ($cluster) => $noEmail->evaluate($cluster));
```

### Cas 3 : Recherche textuelle avec motifs LIKE

Effectuer une recherche insensible à la casse sur les noms.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Rechercher les noms commençant par 'john'
$startsWithJohn = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');

// Rechercher les noms contenant 'doe'
$containsDoe = new ConditionNode('name', ComparisonOperator::LIKE, '%doe%');

// Rechercher les noms se terminant par 'son'
$endsWithSon = new ConditionNode('name', ComparisonOperator::LIKE, '%son');

$collection->filter(fn ($cluster) => $startsWithJohn->evaluate($cluster));
```

### Cas 4 : Comparaisons numériques

Filtrer les clusters par âge ou score.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Clusters avec âge >= 18
$adults = new ConditionNode('age', ComparisonOperator::GREATER_THAN_OR_EQUAL, '18');

// Clusters avec score > 80
$highScore = new ConditionNode('score', ComparisonOperator::GREATER_THAN, '80');

// Clusters avec score entre 60 et 90
$between = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('score', ComparisonOperator::GREATER_THAN_OR_EQUAL, '60'),
    new ConditionNode('score', ComparisonOperator::LESS_THAN_OR_EQUAL, '90')
);
```

### Cas 5 : Conditions de valeur manquante

Comportement spécifique pour les clés manquantes avec certaines valeurs.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$nodeFalse = new ConditionNode('active', ComparisonOperator::EQUAL, 'false');
$nodeNull = new ConditionNode('deleted_at', ComparisonOperator::EQUAL, 'null');

$cluster = new ClusterVO(['status' => 'active']);
// Les clés manquantes sont considérées comme 'false' ou 'null'
$resultFalse = $nodeFalse->evaluate($cluster); // true
$resultNull = $nodeNull->evaluate($cluster);   // true
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Opérateur non supporté en SQL | `InvalidArgumentException` | `Unsupported operator: {operator_name}` |
| Clé JSON invalide | `InvalidArgumentException` | `Invalid JSON key: {key}` |
| Opérateur non supporté en Eloquent | `InvalidArgumentException` | `Unsupported operator: {operator_name}` |

**Note :** Les opérateurs `EQUAL_LOOSE`, `EQUAL_STRICT`, `NOT_EQUAL_STRICT`, et `SPACESHIP` sont supportés uniquement en PHP (évaluation directe) et peuvent ne pas être implémentés dans toutes les bases de données.

---

## Intégration

### Avec `ClusterQuery`
```php
<?php

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;

$clusterQuery = new ClusterQuery();
$ast = $clusterQuery->parse('status=active');
// $ast est un ConditionNode

$result = $clusterQuery->filter($collection, 'status=active');
// Le parser génère un ConditionNode qui évalue la collection
```

### Avec `GroupNode`
```php
<?php

use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;

// Groupe AND de deux conditions
$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('status', ComparisonOperator::EQUAL, 'active'),
    new ConditionNode('role', ComparisonOperator::EQUAL, 'admin')
);
```

### Avec `SubConditionNode`
```php
<?php

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Condition sur un sous-objet
$sub = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);
// Le ConditionNode est utilisé comme condition interne du SubConditionNode
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `evaluate()` | **O(1)** | Accès direct au tableau associatif, comparaison simple |
| `toSql()` | **O(1)** | Génération de string simple, pas de boucle |
| `toEloquent()` | **O(1)** | Application d'un whereRaw, pas de boucle |
| Mémoire | **O(1)** | Stocke uniquement clé, opérateur et valeur |

**Optimisations :**
- **Accès direct** : Utilise `array_key_exists()` pour un accès O(1)
- **Court-circuit** : EXISTS/NOT_EXISTS sont évalués immédiatement
- **Pas de cache** : Chaque appel `evaluate()` recalcule (les données peuvent changer)
- **Comparaisons natives** : Utilise les opérateurs PHP pour la comparaison

**Considérations :**
- Les comparaisons de chaînes sont insensibles à la casse (utilisation de `strtolower` ou `LOWER()` en SQL)
- Les opérateurs numériques (`<`, `>`, etc.) fonctionnent sur des chaînes converties en numérique
- Le pattern LIKE est échappé pour les caractères spéciaux `%`, `_`, `\`

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet (types, énumérations) |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (utilise PHP 8+ uniquement) |

| Base de données | Support |
|-----------------|---------|
| MySQL 5.7+ | ✅ Complet (JSON_EXTRACT, LOWER) |
| PostgreSQL 10+ | ✅ Complet (opérateur -> et ->>, LOWER) |
| SQLite 3.10+ | ✅ Complet (json_extract, LOWER) |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;

// 1. Construction d'une condition composite
// (status = 'active' AND (age >= 25 OR score > 80))
$statusCondition = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');

$ageCondition = new ConditionNode('age', ComparisonOperator::GREATER_THAN_OR_EQUAL, '25');
$scoreCondition = new ConditionNode('score', ComparisonOperator::GREATER_THAN, '80');
$ageOrScore = new GroupNode(LogicalOperator::OR, $ageCondition, $scoreCondition);

$finalCondition = new GroupNode(LogicalOperator::AND, $statusCondition, $ageOrScore);

// 2. Évaluation sur une collection
$collection = new ClusterVOCollection([
    new ClusterVO(['status' => 'active', 'age' => '30', 'score' => '75']), // true
    new ClusterVO(['status' => 'active', 'age' => '22', 'score' => '85']), // true
    new ClusterVO(['status' => 'inactive', 'age' => '30', 'score' => '90']), // false
    new ClusterVO(['status' => 'active', 'age' => '20', 'score' => '70']), // false
]);

$filtered = $collection->filter(
    fn (ClusterVO $cluster) => $finalCondition->evaluate($cluster)
);
// Résultat : 2 clusters

// 3. Génération SQL (MySQL)
$sql = $statusCondition->toSql('clusters', DatabaseDriver::MYSQL);
// "LOWER(JSON_EXTRACT(clusters, '$.\"status\"')) = LOWER('active')"

// 4. Génération SQL (SQLite)
$sql = $statusCondition->toSql('clusters', DatabaseDriver::SQLITE);
// "LOWER(json_extract(clusters, '$.status')) = LOWER('active')"

// 5. Génération SQL (PostgreSQL)
$sql = $statusCondition->toSql('clusters', DatabaseDriver::PGSQL);
// "LOWER(clusters->>'status') = LOWER('active')"

// 6. Application à Eloquent
$query = TestCluster::query();
$statusCondition->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
$results = $query->get();
echo "Nombre de résultats : " . $results->count();

// 7. Cas d'usage : recherche LIKE insensible à la casse
$likeNode = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');
$query = TestCluster::query();
$likeNode->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
$results = $query->get();
// Tous les noms commençant par 'john' (insensible à la casse)

// 8. Cas d'usage : vérification d'existence
$existsNode = new ConditionNode('email', ComparisonOperator::EXISTS);
$query = TestCluster::query();
$existsNode->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
$results = $query->get();
// Tous les clusters ayant un champ 'email'
```

---

## Voir aussi

- `GroupNode` - Groupe de conditions avec opérateurs logiques
- `SubConditionNode` - Condition sur sous-objets (tableaux)
- `ClusterQuery` - Service principal pour l'évaluation des requêtes
- `ComparisonOperator` - Énumération des opérateurs de comparaison
- `NodeInterface` - Interface commune à tous les nœuds de l'AST