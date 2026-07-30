# SubConditionNode - Référence Technique

## Description

Évalue une condition sur un sous-objet ou un tableau imbriqué dans les données JSON d'un cluster.

## Hiérarchie / Implémentations

```
Node (abstract)
    └── SubConditionNode
```

**Interfaces implémentées :**
- `NodeInterface`

## Rôle principal

`SubConditionNode` permet de naviguer dans une structure JSON hiérarchique et d'appliquer une condition sur une valeur située à un chemin donné. Il est conçu pour les requêtes sur des tableaux (comme `addresses[city=kinshasa]`) ou des chemins imbriqués (comme `settings.notifications.email=true`). Il supporte des cas spéciaux comme `__empty__` pour vérifier qu'un tableau n'est pas vide, et `*` pour vérifier l'existence d'éléments dans un tableau.

---

## API / Méthodes publiques

### `__construct(string $path, NodeInterface $condition)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Chemin JSON pointé par des points (ex: `addresses`, `settings.notifications`) |
| `$condition` | `NodeInterface` | Nœud condition à appliquer sur la valeur trouvée |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Condition sur un tableau d'adresses
$sub = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);

// Condition sur un chemin imbriqué
$sub = new SubConditionNode(
    'settings.notifications',
    new ConditionNode('email', ComparisonOperator::EQUAL, 'true')
);
```

---

### `getPath(): string`

Retourne le chemin de la sous-condition.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - Le chemin d'accès

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$sub = new SubConditionNode('addresses', $condition);
$path = $sub->getPath(); // 'addresses'
```

---

### `getCondition(): NodeInterface`

Retourne le nœud condition attaché à la sous-condition.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `NodeInterface` - Le nœud condition

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
$sub = new SubConditionNode('addresses', $condition);
$innerCondition = $sub->getCondition(); // $condition
```

---

### `evaluate(ClusterVO $data): bool`

Évalue la sous-condition contre les données d'un cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `ClusterVO` | Données du cluster à évaluer |

**Retourne :** `bool` - `true` si au moins un élément du tableau satisfait la condition

**Comportement spécifique :**
- **Chemin inexistant** : Retourne `false` (sauf NOT_EXISTS)
- **__empty__** : Vérifie si le tableau n'est pas vide
- **Wildcard EXISTS (*)** : Vérifie si le tableau contient des éléments
- **NOT_EXISTS** : Vérifie si la clé n'existe dans aucun élément du tableau
- **Autres conditions** : Évalue chaque élément du tableau, retourne `true` dès qu'un élément satisfait la condition

**Exceptions :** Aucune

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

$cluster = new ClusterVO([
    'addresses' => [
        ['city' => 'kinshasa', 'country' => 'rdc'],
        ['city' => 'paris', 'country' => 'france']
    ]
]);

$sub = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);

$result = $sub->evaluate($cluster); // true
```

---

### `toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

Génère une expression SQL pour la sous-condition.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | Nom de la colonne JSON |
| `$driver` | `DatabaseDriver` | Driver de base de données (MYSQL, PGSQL, SQLITE) |

**Retourne :** `string` - Expression SQL pour la sous-condition

**Comportement spécifique :**
- **__empty__** : Génère une vérification de longueur du tableau (`JSON_LENGTH > 0`)
- **Wildcard EXISTS (*)** : Génère un `EXISTS` sur le tableau
- **NOT_EXISTS** : Génère un `NOT EXISTS` avec la condition inversée (IS NOT NULL)
- **Autres conditions** : Génère un `EXISTS` avec une sous-requête

**Exceptions :** Aucune

**Exemple :**
```php
<?php

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$sub = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);

$sql = $sub->toSql('clusters', DatabaseDriver::SQLITE);
// "EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE LOWER(json_extract(value, '$.city')) = LOWER('kinshasa'))"
```

---

### `toEloquent(Builder $query, string $column, DatabaseDriver $driver): void`

Applique la sous-condition à un constructeur de requête Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Constructeur de requête Eloquent |
| `$column` | `string` | Nom de la colonne JSON |
| `$driver` | `DatabaseDriver` | Driver de base de données |

**Retourne :** `void` (modifie `$query` par référence)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$sub = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);

$query = TestCluster::query();
$sub->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

$results = $query->get(); // Clusters avec une adresse à Kinshasa
```

---

### `getChildren(): array`

Retourne les nœuds enfants de la sous-condition.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, NodeInterface>` - Tableau contenant le nœud condition

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$sub = new SubConditionNode('addresses', $condition);
$children = $sub->getChildren(); // [$condition]
```

---

## Cas d'utilisation

### Cas 1 : Recherche dans un tableau d'objets

Trouver les clusters ayant une adresse dans une ville spécifique.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

// Recherche des clusters avec une adresse à Kinshasa
$sub = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);

$collection = new ClusterVOCollection([
    new ClusterVO([
        'name' => 'Cluster A',
        'addresses' => [
            ['city' => 'kinshasa', 'country' => 'rdc'],
            ['city' => 'paris', 'country' => 'france']
        ]
    ]),
    new ClusterVO([
        'name' => 'Cluster B',
        'addresses' => [
            ['city' => 'london', 'country' => 'uk']
        ]
    ])
]);

$filtered = $collection->filter(
    fn (ClusterVO $cluster) => $sub->evaluate($cluster)
);
// Résultat : Cluster A (contient une adresse à Kinshasa)
```

### Cas 2 : Conditions composites sur sous-objets

Rechercher dans un tableau avec plusieurs critères.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Recherche des clusters avec une adresse à Kinshasa ET en RDC
$cityCondition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
$countryCondition = new ConditionNode('country', ComparisonOperator::EQUAL, 'rdc');

$group = new GroupNode(
    LogicalOperator::AND,
    $cityCondition,
    $countryCondition
);

$sub = new SubConditionNode('addresses', $group);

// La requête trouve les clusters ayant au moins une adresse qui satisfait les deux critères
```

### Cas 3 : Vérification d'existence dans un tableau

Trouver les clusters qui ont au moins une adresse (tableau non vide).

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Méthode 1 : Utilisation de __empty__
$hasAddresses = new SubConditionNode(
    'addresses',
    new ConditionNode('__empty__', ComparisonOperator::EQUAL)
);

// Méthode 2 : Utilisation du wildcard EXISTS
$hasAddresses = new SubConditionNode(
    'addresses',
    new ConditionNode('*', ComparisonOperator::EXISTS)
);

// Les deux méthodes retournent true si le tableau 'addresses' existe et n'est pas vide
```

### Cas 4 : Recherche d'absence

Trouver les clusters où aucune adresse ne contient une certaine ville.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Clusters sans adresse à Paris (NOT EXISTS)
$noParisAddress = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::NOT_EXISTS)
);

// Trouve les clusters où la clé 'city' n'existe dans AUCUNE adresse
// ou le tableau d'adresses est vide/inexistant
```

### Cas 5 : Chemins imbriqués profonds

Naviguer dans des structures JSON complexes.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Structure : settings.notifications.email
$emailNotifications = new SubConditionNode(
    'settings.notifications',
    new ConditionNode('email', ComparisonOperator::EQUAL, 'true')
);

// Structure : user.contact.address.city
$addressInCity = new SubConditionNode(
    'user.contact.address',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);
```

---

## Gestion des erreurs

Le `SubConditionNode` ne lève pas d'exceptions directement. Les erreurs peuvent survenir lors de l'utilisation des méthodes `toSql()` ou `toEloquent()` si le nœud condition interne lève des exceptions.

| Situation | Comportement | Détails |
|-----------|--------------|---------|
| Chemin inexistant | Retourne `false` (ou `true` pour NOT_EXISTS) | Évaluation PHP directe |
| Chemin non-tableau | Retourne `false` | La valeur trouvée n'est pas un tableau |
| Tableau vide | Retourne `false` | Aucun élément à évaluer |
| Condition NULL | Retourne `false` | La valeur du tableau est null |
| NOT_EXISTS sur tableau vide | Retourne `true` | Aucun élément donc la clé n'existe dans aucun |

**Note :** Les exceptions des sous-conditions (comme `ConditionNode`) sont remontées si nécessaire.

---

## Intégration

### Avec `ClusterQuery`
```php
<?php

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;

$clusterQuery = new ClusterQuery();
$ast = $clusterQuery->parse('addresses[city=kinshasa]');
// $ast est un SubConditionNode

$result = $clusterQuery->filter($collection, 'addresses[city=kinshasa]');
// Le parser génère un SubConditionNode qui évalue la collection
```

### Avec `ConditionNode`
```php
<?php

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Les ConditionNode sont utilisés comme conditions internes
$sub = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);
```

### Avec `GroupNode`
```php
<?php

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Conditions composites sur sous-objets
$group = new GroupNode(
    LogicalOperator::AND,
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa'),
    new ConditionNode('country', ComparisonOperator::EQUAL, 'rdc')
);

$sub = new SubConditionNode('addresses', $group);
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `evaluate()` | **O(n)** | Parcourt le tableau d'éléments, s'arrête au premier match |
| `toSql()` | **O(n)** | Génère une sous-requête avec `EXISTS` |
| `toEloquent()` | **O(n)** | Applique un `whereRaw` avec `EXISTS`, optimisé par la BDD |
| Mémoire | **O(1)** | Stocke uniquement le chemin et la condition |

**Optimisations :**
- **Court-circuit** : S'arrête dès qu'un élément satisfait la condition
- **Cas spéciaux optimisés** : `__empty__` et wildcard EXISTS utilisent des fonctions de longueur de tableau (plus rapides)
- **Sous-requêtes SQL** : Les bases de données optimisent les requêtes `EXISTS`

**Considérations :**
- Les tableaux de grande taille peuvent être lents en PHP (parcours complet si aucun match)
- Les sous-requêtes SQL peuvent être lourdes sur de très grands jeux de données
- Les fonctions JSON (`json_each`, `JSON_TABLE`) peuvent avoir un coût en performance

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté |

| Base de données | Support | Détails |
|-----------------|---------|---------|
| MySQL 5.7+ | ✅ Complet | Utilise `JSON_TABLE` (MySQL 8.0+) ou simulation |
| MySQL 5.7 | ⚠️ Limité | `JSON_TABLE` n'existe pas, nécessite une approche alternative |
| PostgreSQL 10+ | ✅ Complet | Utilise `jsonb_array_elements` |
| SQLite 3.10+ | ✅ Complet | Utilise `json_each` |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;

// 1. Construction d'une sous-condition complexe
// Trouver les clusters ayant au moins une adresse à Kinshasa OU à Paris
// ET où le pays est RDC OU France
$city1 = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
$city2 = new ConditionNode('city', ComparisonOperator::EQUAL, 'paris');
$cityOr = new GroupNode(LogicalOperator::OR, $city1, $city2);

$country1 = new ConditionNode('country', ComparisonOperator::EQUAL, 'rdc');
$country2 = new ConditionNode('country', ComparisonOperator::EQUAL, 'france');
$countryOr = new GroupNode(LogicalOperator::OR, $country1, $country2);

$addressGroup = new GroupNode(LogicalOperator::AND, $cityOr, $countryOr);
$subCondition = new SubConditionNode('addresses', $addressGroup);

// 2. Évaluation sur une collection
$collection = new ClusterVOCollection([
    new ClusterVO([
        'name' => 'Cluster A',
        'addresses' => [
            ['city' => 'kinshasa', 'country' => 'rdc'], // match
            ['city' => 'london', 'country' => 'uk']
        ]
    ]),
    new ClusterVO([
        'name' => 'Cluster B',
        'addresses' => [
            ['city' => 'paris', 'country' => 'france'] // match
        ]
    ]),
    new ClusterVO([
        'name' => 'Cluster C',
        'addresses' => [
            ['city' => 'kinshasa', 'country' => 'france'], // ville match, pays mismatch
            ['city' => 'london', 'country' => 'uk']
        ]
    ]),
]);

$filtered = $collection->filter(
    fn (ClusterVO $cluster) => $subCondition->evaluate($cluster)
);
// Résultat : Cluster A et Cluster B (2 clusters)

// 3. Génération SQL pour SQLite
$sql = $subCondition->toSql('clusters', DatabaseDriver::SQLITE);
echo $sql;
// EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE 
//   ((LOWER(json_extract(value, '$.city')) = LOWER('kinshasa') OR 
//     LOWER(json_extract(value, '$.city')) = LOWER('paris')) AND 
//    (LOWER(json_extract(value, '$.country')) = LOWER('rdc') OR 
//     LOWER(json_extract(value, '$.country')) = LOWER('france'))))

// 4. Génération SQL pour MySQL
$sql = $subCondition->toSql('clusters', DatabaseDriver::MYSQL);
echo $sql;
// EXISTS (SELECT 1 FROM JSON_TABLE(clusters, '$.addresses[*]' COLUMNS(value JSON PATH '$')) AS jt WHERE 
//   ((LOWER(JSON_EXTRACT(value, '$."city"')) = LOWER('kinshasa') OR 
//     LOWER(JSON_EXTRACT(value, '$."city"')) = LOWER('paris')) AND 
//    (LOWER(JSON_EXTRACT(value, '$."country"')) = LOWER('rdc') OR 
//     LOWER(JSON_EXTRACT(value, '$."country"')) = LOWER('france'))))

// 5. Application à Eloquent (SQLite)
$query = TestCluster::query();
$subCondition->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);
$results = $query->get();
echo "Nombre de résultats : " . $results->count();

// 6. Cas d'usage : Vérification de tableau vide
$emptySub = new SubConditionNode(
    'addresses',
    new ConditionNode('__empty__', ComparisonOperator::EQUAL)
);

$sqlEmpty = $emptySub->toSql('clusters', DatabaseDriver::SQLITE);
// "json_array_length(clusters, '$.addresses') > 0"

// 7. Cas d'usage : NOT EXISTS sur un tableau
$notExistsSub = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::NOT_EXISTS)
);

$sqlNotExists = $notExistsSub->toSql('clusters', DatabaseDriver::SQLITE);
// "NOT EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE json_extract(value, '$.city') IS NOT NULL)"
```

---

## Voir aussi

- `ConditionNode` - Nœud de condition simple (utilisé comme condition interne)
- `GroupNode` - Groupe de conditions (peut être utilisé comme condition interne)
- `ClusterQuery` - Service principal pour l'évaluation des requêtes
- `DatabaseDriver` - Énumération des drivers de base de données
- `NodeInterface` - Interface commune à tous les nœuds de l'AST