# ClusterQuery - Technical Reference

## Description

Le moteur central d'analyse et d'évaluation des requêtes Cluster. Il orchestre l'ensemble du système en assurant le parsing, le filtrage, l'évaluation, la génération SQL et l'application aux requêtes Eloquent.

## Hiérarchie

```
ClusterQuery
    └── Utilise ParserInterface
```

## Rôle principal

Point d'entrée unique pour toutes les opérations sur les requêtes Cluster. Il coordonne :
- Le parsing des requêtes en AST
- Le filtrage des collections de clusters
- L'évaluation de clusters individuels
- La génération de SQL adaptée aux drivers
- L'application des requêtes aux builders Eloquent

---

## API

### `__construct(?ParserInterface $parser = null)`

Initialise le moteur avec un parseur optionnel.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parser` | `ParserInterface|null` | Instance du parseur (créé par défaut si null) |

**Exemple :**
```php
// Parseur par défaut
$clusterQuery = new ClusterQuery();

// Parseur personnalisé
$clusterQuery = new ClusterQuery(new CustomParser());
```

---

### `parse(string $query): NodeInterface`

Parse une chaîne de requête en un Arbre Syntaxique Abstrait (AST).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | La requête à parser |

**Retourne :** `NodeInterface` - Le nœud racine de l'AST

**Exemple :**
```php
$ast = $clusterQuery->parse('status=active & role=admin');
// GroupNode contenant deux ConditionNode
```

---

### `filter(ClusterVOCollection $clusters, string $query): ClusterVOCollection`

Filtre une collection de clusters avec une expression de requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$clusters` | `ClusterVOCollection` | La collection à filtrer |
| `$query` | `string` | L'expression de filtrage |

**Retourne :** `ClusterVOCollection` - La collection filtrée

**Exemple :**
```php
$filtered = $clusterQuery->filter($clusters, 'status=active & role=admin');
```

---

### `matches(ClusterVO $cluster, string $query): bool`

Détermine si un cluster individuel correspond à une requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Le cluster à évaluer |
| `$query` | `string` | L'expression de requête |

**Retourne :** `bool` - `true` si le cluster correspond

**Exemple :**
```php
$matches = $clusterQuery->matches($cluster, 'score > 80');
// true si le score est supérieur à 80
```

---

### `toSql(string $column, string $query, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

Convertit une requête en clause SQL WHERE.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | Le nom de la colonne JSON en base de données |
| `$query` | `string` | L'expression de requête |
| `$driver` | `DatabaseDriver` | Le driver de base de données cible |

**Retourne :** `string` - La clause SQL générée

**Exemple :**
```php
$sql = $clusterQuery->toSql('clusters', 'status=active', DatabaseDriver::SQLITE);
// json_extract(clusters, '$.status') IS NOT NULL ...
```

---

### `applyToEloquent(Builder $query, string $column, string $clusterQuery, DatabaseDriver $driver = DatabaseDriver::MYSQL): void`

Applique une requête à un builder Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Le builder Eloquent à modifier |
| `$column` | `string` | Le nom de la colonne JSON |
| `$clusterQuery` | `string` | L'expression de requête |
| `$driver` | `DatabaseDriver` | Le driver de base de données cible |

**Exemple :**
```php
$query = User::query();
$clusterQuery->applyToEloquent($query, 'clusters', 'status=active', DatabaseDriver::SQLITE);
$users = $query->get();
```

---

## Cas d'utilisation

### Cas 1 : Filtrage de collection en mémoire

```php
<?php

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$clusterQuery = new ClusterQuery();

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO(['status' => 'active', 'role' => 'admin']));
$collection->add(new ClusterVO(['status' => 'active', 'role' => 'doctor']));
$collection->add(new ClusterVO(['status' => 'inactive', 'role' => 'admin']));

// Filtrage simple
$filtered = $clusterQuery->filter($collection, 'status=active');
// 2 clusters

// Filtrage avec AND
$filtered = $clusterQuery->filter($collection, 'status=active & role=admin');
// 1 cluster (admin actif)

// Filtrage avec OR
$filtered = $clusterQuery->filter($collection, 'status=active | role=admin');
// 3 clusters (tous)
```

### Cas 2 : Évaluation de cluster individuel

```php
$cluster = new ClusterVO([
    'status' => 'active',
    'role' => 'admin',
    'age' => 30,
]);

$result = $clusterQuery->matches($cluster, 'status=active & role=admin');
// true

$result = $clusterQuery->matches($cluster, 'age>35');
// false
```

### Cas 3 : Génération SQL

```php
// SQLite
$sql = $clusterQuery->toSql('clusters', 'status=active', DatabaseDriver::SQLITE);
// LOWER(json_extract(clusters, '$.status')) = LOWER('active')

// MySQL
$sql = $clusterQuery->toSql('clusters', 'status=active', DatabaseDriver::MYSQL);
// LOWER(JSON_EXTRACT(clusters, '$."status"')) = LOWER('active')

// PostgreSQL
$sql = $clusterQuery->toSql('clusters', 'status=active', DatabaseDriver::PGSQL);
// LOWER(clusters->>'status') = LOWER('active')

// Avec fonction SQL
$sql = $clusterQuery->toSql('clusters', 'COUNT(addresses) > 2', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses') > 2
```

### Cas 4 : Application à Eloquent

```php
use App\Models\User;

$query = User::query();

// Condition simple
$clusterQuery->applyToEloquent($query, 'clusters', 'status=active', DatabaseDriver::SQLITE);

// Conditions multiples
$clusterQuery->applyToEloquent($query, 'clusters', 'status=active & role=admin', DatabaseDriver::SQLITE);

// Sous-condition
$clusterQuery->applyToEloquent($query, 'clusters', 'addresses[city=Kinshasa]', DatabaseDriver::SQLITE);

// Fonction SQL
$clusterQuery->applyToEloquent($query, 'clusters', 'COUNT(addresses) > 2', DatabaseDriver::SQLITE);

$users = $query->get();
```

### Cas 5 : Chaînage de conditions

```php
$query = User::query();

$clusterQuery->applyToEloquent($query, 'clusters', 'status=active', DatabaseDriver::SQLITE);
$clusterQuery->applyToEloquent($query, 'clusters', 'role=admin', DatabaseDriver::SQLITE);
$clusterQuery->applyToEloquent($query, 'clusters', 'age>25', DatabaseDriver::SQLITE);

$users = $query->get();
// Utilisateurs actifs, admins, de plus de 25 ans
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Requête invalide | `RuntimeException` | Message personnalisé selon l'erreur de parsing |
| Fonction inconnue | `RuntimeException` | `Unknown function "{name}"` |
| Opérateur invalide | `RuntimeException` | `Invalid operator "{op}". Allowed: ...` |

---

## Performance

- **Parsing :** Les AST sont mis en cache par requête via le parseur
- **Filter :** O(n) où n est le nombre de clusters
- **SQL :** Génération à la volée, pas de cache
- **Évaluation :** L'AST est réutilisé pour plusieurs clusters

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
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use App\Models\User;

// ==================== INSTANCIATION ====================

$clusterQuery = new ClusterQuery();

// ==================== COLLECTION EN MÉMOIRE ====================

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John Doe',
    'status' => 'active',
    'role' => 'admin',
    'age' => 30,
    'addresses' => [
        ['city' => 'Kinshasa'],
        ['city' => 'Paris'],
    ],
    'scores' => [80, 90, 85],
]));

$collection->add(new ClusterVO([
    'name' => 'Jane Smith',
    'status' => 'inactive',
    'role' => 'doctor',
    'age' => 25,
    'addresses' => [
        ['city' => 'Paris'],
    ],
    'scores' => [70, 75, 80],
]));

$collection->add(new ClusterVO([
    'name' => 'Bob Johnson',
    'status' => 'active',
    'role' => 'doctor',
    'age' => 35,
    'addresses' => [
        ['city' => 'Kinshasa'],
        ['city' => 'London'],
        ['city' => 'Paris'],
    ],
    'scores' => [95, 98, 92],
]));

// ==================== FILTRAGE ====================

// Simple
$active = $clusterQuery->filter($collection, 'status=active');
echo "Active: " . $active->count() . "\n"; // 2

// Avec AND
$activeAdmins = $clusterQuery->filter($collection, 'status=active & role=admin');
echo "Active admins: " . $activeAdmins->count() . "\n"; // 1

// Avec sous-condition
$kinshasa = $clusterQuery->filter($collection, 'addresses[city=Kinshasa]');
echo "In Kinshasa: " . $kinshasa->count() . "\n"; // 2

// Avec fonction SQL
$moreThan2 = $clusterQuery->filter($collection, 'COUNT(addresses) > 2');
echo "More than 2 addresses: " . $moreThan2->count() . "\n"; // 1

// Complexe
$complex = $clusterQuery->filter(
    $collection,
    'status=active & COUNT(addresses) > 1 & AVG(scores) >= 85'
);
echo "Complex filter: " . $complex->count() . "\n"; // 1 (Bob)

// ==================== ÉVALUATION INDIVIDUELLE ====================

$cluster = $collection->first();
$matches = $clusterQuery->matches($cluster, 'status=active & role=admin');
var_dump($matches); // true

// ==================== GÉNÉRATION SQL ====================

$sql = $clusterQuery->toSql('clusters', 'status=active & role=admin', DatabaseDriver::SQLITE);
echo "SQL: $sql\n";

// ==================== APPLICATION ELOQUENT ====================

$query = User::query();

$clusterQuery->applyToEloquent(
    $query,
    'clusters',
    'status=active & role=admin & age>=25',
    DatabaseDriver::SQLITE
);

$users = $query->get();
```

---

## Voir aussi

- `Parser` - Analyseur de requêtes
- `NodeInterface` - Interface des nœuds de l'AST
- `DatabaseDriver` - Énumération des drivers supportés
- `ClusterVOCollection` - Collection de clusters
- `ClusterVO` - Value Object représentant un cluster