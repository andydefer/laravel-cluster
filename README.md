# Laravel Cluster - Documentation Complète

## Table des matières

1. [Installation](#1-installation)
2. [Architecture du package](#2-architecture-du-package)
3. [Le moteur central : ClusterQuery](#3-le-moteur-central--clusterquery)
4. [Le service façade : ClusterService](#4-le-service-façade--clusterservice)
5. [Structure des données : ClusterVO](#5-structure-des-données--clustervo)
6. [Eloquent Cast : ClusterCast](#6-eloquent-cast--clustercast)
7. [La collection intelligente : ClusterVOCollection](#7-la-collection-intelligente--clustervocollection)
8. [Filtrer des collections en mémoire](#8-filtrer-des-collections-en-mémoire)
9. [Générer du SQL pour différents drivers](#9-générer-du-sql-pour-différents-drivers)
10. [Fonctions SQLite personnalisées](#10-fonctions-sqlite-personnalisées)
11. [Intégration avec Eloquent](#11-intégration-avec-eloquent)
12. [Les Macros Laravel](#12-les-macros-laravel)
13. [Les fonctions SQL d'agrégation](#13-les-fonctions-sql-dagrégation)
14. [Les sous-conditions sur tableaux](#14-les-sous-conditions-sur-tableaux)
15. [Les opérateurs EXISTS et NOT_EXISTS](#15-les-opérateurs-exists-et-not-exists)
16. [Les opérateurs LIKE et NOT_LIKE](#16-les-opérateurs-like-et-not-like)
17. [Les fonctions d'agrégation en mémoire](#17-les-fonctions-dagrégation-en-mémoire)
18. [Créer des fonctions personnalisées](#18-créer-des-fonctions-personnalisées)
19. [Parser et AST](#19-parser-et-ast)
20. [Référence des opérateurs](#20-référence-des-opérateurs)
21. [Référence des méthodes de ClusterVOCollection](#21-référence-des-méthodes-de-clustervocollection)
22. [Cas d'usage concrets](#22-cas-dusage-concrets)
23. [Débogage et résolution des problèmes](#23-débogage-et-résolution-des-problèmes)
24. [Performance et bonnes pratiques](#24-performance-et-bonnes-pratiques)

---

## 1. Installation

### 1.1 Prérequis

- PHP 8.1 ou supérieur
- Laravel 10.x, 11.x, 12.x, 13.x, 14.x ou 15.x

### 1.2 Installation via Composer

```bash
composer require andydefer/laravel-cluster
```

### 1.3 Configuration

Le package s'enregistre automatiquement. Si vous utilisez une version de Laravel sans auto-discovery :

```php
// config/app.php
'providers' => [
    // ...
    AndyDefer\LaravelCluster\Providers\ClusterServiceProvider::class,
],
```

### 1.4 Structure du Service Provider

```
src/
├── Providers/
│   └── ClusterServiceProvider.php    # Enregistrement des services
├── Utilities/
│   ├── ClusterMacroRegistrar.php    # Enregistrement des macros
│   └── SqliteFunctionRegistrar.php  # Fonctions SQLite personnalisées
├── Casts/
│   └── ClusterCast.php              # Cast Eloquent
└── ...
```

### 1.5 Injection de dépendances

```php
<?php

namespace App\Services;

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;

class UserFilterService
{
    public function __construct(
        private readonly ClusterService $clusterService,
        private readonly ClusterQuery $clusterQuery,
        private readonly SqlFunctionRegistry $sqlRegistry
    ) {}

    public function filter(array $criteria)
    {
        // Utilisation de $this->clusterService...
    }
}
```

---

## 2. Architecture du package

### 2.1 Flux de traitement d'une requête

```php
use AndyDefer\LaravelCluster\Lexer;
use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\ClusterQuery;

// 1. Vous écrivez une requête
$query = 'status=active & age>25 & COUNT(addresses)>2';

// 2. Le Lexer tokenise l'expression
$lexer = new Lexer();
$tokens = $lexer->tokenize($query);

// 3. Le Parser construit l'AST
$parser = new Parser();
$ast = $parser->parse($query);

// 4. Le ClusterQuery exécute l'AST
$clusterQuery = new ClusterQuery();

// Évaluation en mémoire
$result = $clusterQuery->filter($collection, $query);

// Génération SQL
$sql = $clusterQuery->toSql('clusters', $query, DatabaseDriver::MYSQL);

// Application Eloquent
$clusterQuery->applyToEloquent($queryBuilder, 'clusters', $query, DatabaseDriver::MYSQL);
```

### 2.2 Les composants clés

```php
use AndyDefer\LaravelCluster\Lexer;
use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;

// 1. Lexer - Tokenise une expression
$tokens = (new Lexer())->tokenize('status=active');

// 2. Parser - Construit l'AST
$ast = (new Parser())->parse('status=active');

// 3. ClusterQuery - Moteur central
$engine = new ClusterQuery();
$filtered = $engine->filter($clusters, 'status=active');

// 4. ClusterService - Façade
$service = new ClusterService($engine);
$filtered = $service->filter($clusters, 'status=active');

// 5. Registres - Gestion des fonctions
$sqlRegistry = new SqlFunctionRegistry();
$aggRegistry = new AggregateFunctionRegistry();
```

---

## 3. Le moteur central : ClusterQuery

`ClusterQuery` est le cœur du package. Il orchestre toutes les opérations.

### 3.1 Création

```php
use AndyDefer\LaravelCluster\ClusterQuery;

// Création simple
$engine = new ClusterQuery();

// Via le conteneur Laravel
$engine = app(ClusterQuery::class);
```

### 3.2 Parser une requête

```php
use AndyDefer\LaravelCluster\ClusterQuery;

$engine = new ClusterQuery();

// Parse une requête simple
$ast = $engine->parse('status=active');

// Parse une requête complexe
$ast = $engine->parse('status=active & (role=admin | role=doctor)');

// Parse une requête avec fonction SQL
$ast = $engine->parse('COUNT(addresses) > 2');

// Parse une sous-condition
$ast = $engine->parse('addresses[city=Kinshasa]');
```

### 3.3 Filtrer une collection en mémoire

```php
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$engine = new ClusterQuery();

// Créer une collection
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO(['status' => 'active', 'age' => 25]));
$clusters->add(new ClusterVO(['status' => 'inactive', 'age' => 30]));
$clusters->add(new ClusterVO(['status' => 'active', 'age' => 18]));

// Filtrer
$filtered = $engine->filter($clusters, 'status=active & age>=20');
// Résultat : 1 cluster (age=25)
```

### 3.4 Tester un cluster individuel

```php
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$engine = new ClusterQuery();

$cluster = new ClusterVO([
    'status' => 'active',
    'role' => 'admin',
    'age' => 30,
]);

// Test simple
$matches = $engine->matches($cluster, 'status=active'); // true

// Test avec AND
$matches = $engine->matches($cluster, 'status=active & role=admin'); // true

// Test avec OR
$matches = $engine->matches($cluster, 'status=active | status=pending'); // true

// Test avec fonction
$matches = $engine->matches($cluster, 'age>25'); // true
```

### 3.5 Générer du SQL

```php
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$engine = new ClusterQuery();

// SQL pour MySQL
$sql = $engine->toSql('clusters', 'status=active', DatabaseDriver::MYSQL);
// JSON_EXTRACT(clusters, '$."status"') = 'active'

// SQL pour SQLite
$sql = $engine->toSql('clusters', 'status=active', DatabaseDriver::SQLITE);
// json_extract(clusters, '$.status') = 'active'

// SQL pour PostgreSQL
$sql = $engine->toSql('clusters', 'status=active', DatabaseDriver::PGSQL);
// clusters->>'status' = 'active'

// SQL avec fonction
$sql = $engine->toSql('clusters', 'COUNT(addresses) > 2', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses') > 2
```

### 3.6 Appliquer à Eloquent

```php
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

$engine = new ClusterQuery();

$query = User::query();

// Condition simple
$engine->applyToEloquent($query, 'clusters', 'status=active', DatabaseDriver::MYSQL);

// Conditions multiples
$engine->applyToEloquent($query, 'clusters', 'status=active & role=admin', DatabaseDriver::MYSQL);

// Sous-condition
$engine->applyToEloquent($query, 'clusters', 'addresses[city=Kinshasa]', DatabaseDriver::MYSQL);

// Fonction SQL
$engine->applyToEloquent($query, 'clusters', 'COUNT(addresses) > 2', DatabaseDriver::MYSQL);

$users = $query->get();
```

---

## 4. Le service façade : ClusterService

`ClusterService` est une façade qui délègue à `ClusterQuery`.

### 4.1 Création

```php
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\ClusterQuery;

// Création manuelle
$service = new ClusterService(new ClusterQuery());

// Via le conteneur
$service = app(ClusterService::class);
```

### 4.2 Utilisation

```php
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$service = app(ClusterService::class);

// 1. Parser
$ast = $service->parse('status=active');

// 2. Filtrer une collection
$clusters = new ClusterVOCollection();
$filtered = $service->filter($clusters, 'status=active');

// 3. Tester un cluster
$cluster = new ClusterVO(['status' => 'active']);
$matches = $service->matches($cluster, 'status=active'); // true

// 4. Générer du SQL
$sql = $service->toSql('clusters', 'status=active', DatabaseDriver::MYSQL);

// 5. Appliquer à Eloquent
$query = User::query();
$service->applyToEloquent($query, 'clusters', 'status=active', DatabaseDriver::MYSQL);
$users = $query->get();
```

### 4.3 Exemple dans un service Laravel

```php
<?php

namespace App\Services;

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\Product;

class ProductFilterService
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function filterProducts(array $filters): array
    {
        $query = Product::query();

        // Construction de la requête
        $conditions = [];
        foreach ($filters as $key => $value) {
            if ($key === 'min_price') {
                $conditions[] = "price>=$value";
            } elseif ($key === 'max_price') {
                $conditions[] = "price<=$value";
            } elseif ($key === 'category') {
                $conditions[] = "category=$value";
            }
        }

        $queryString = implode(' & ', $conditions);

        if (!empty($queryString)) {
            $this->clusterService->applyToEloquent(
                $query,
                'attributes',
                $queryString,
                DatabaseDriver::MYSQL
            );
        }

        return $query->get()->toArray();
    }
}
```

---

## 5. Structure des données : ClusterVO

`ClusterVO` est le conteneur qui aplatit automatiquement les données JSON pour un accès rapide.

### 5.1 Création d'un ClusterVO

```php
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$cluster = new ClusterVO([
    'id' => 1,
    'name' => 'John Doe',
    'age' => 30,
    'is_active' => 'yes',
    'address' => [
        'city' => 'Paris',
        'country' => 'France',
        'postal_code' => 75000,
    ],
    'tags' => ['php', 'js', 'docker'],
    'settings' => [
        'theme' => 'dark',
        'notifications' => [
            'email' => 'yes',
            'sms' => 'no',
        ],
    ],
]);
```

### 5.2 Accès aux données

```php
// Accès simple
$name = $cluster->get('name'); // 'John Doe'

// Accès par notation pointée
$city = $cluster->get('address.city'); // 'Paris'
$email = $cluster->get('settings.notifications.email'); // 'yes'

// Accès aux tableaux aplatis
$hasPhp = $cluster->get('tags_php'); // 'yes'
$hasJs = $cluster->get('tags_js'); // 'yes'

// Vérification d'existence
if ($cluster->has('address.city')) {
    echo "Ville définie";
}

// Récupération de toutes les clés
$keys = $cluster->keys();

// Récupération des données brutes
$flatData = $cluster->toArray();
$nestedData = $cluster->getUnflattened()->toArray();
```

### 5.3 ArrayAccess

```php
$cluster = new ClusterVO([
    'status' => 'active',
    'role' => 'admin',
    'user' => ['name' => 'John Doe'],
]);

// Accès comme un tableau
echo $cluster['status']; // 'active'
echo $cluster['user.name']; // 'John Doe'

// Vérification d'existence
if (isset($cluster['user.email'])) {
    echo $cluster['user.email'];
}

// Le cluster est immutable
try {
    $cluster['status'] = 'inactive'; // Lance une RuntimeException
} catch (RuntimeException $e) {
    echo "ClusterVO is immutable";
}
```

### 5.4 Création simplifiée avec ClusterVOProxy

```php
use AndyDefer\LaravelCluster\Proxies\ClusterVOProxy;

// Création avec booléens PHP
$cluster = ClusterVOProxy::make([
    'id' => 1,
    'name' => 'John Doe',
    'age' => 30,
    'is_active' => true,        // → 'yes'
    'is_verified' => false,     // → 'no'
    'address' => [
        'city' => 'Paris',
        'country' => 'France',
        'postal_code' => 75000,
    ],
    'tags' => ['php', 'js', 'docker'],
    'settings' => [
        'theme' => 'dark',
        'notifications' => [
            'email' => true,    // → 'yes'
            'sms' => false,     // → 'no'
        ],
    ],
]);

// Accès normalisé
$cluster->get('is_active'); // 'yes'
$cluster->get('is_verified'); // 'no'
$cluster->get('settings.notifications.email'); // 'yes'
$cluster->get('settings.notifications.sms'); // 'no'
```

---

## 6. Eloquent Cast : ClusterCast

### 6.1 Installation dans un modèle

```php
<?php

namespace App\Models;

use AndyDefer\LaravelCluster\Casts\ClusterCast;
use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $casts = [
        'metadata' => ClusterCast::class,
    ];
}
```

### 6.2 Utilisation

```php
// Création avec un tableau
$user = User::create([
    'name' => 'John Doe',
    'metadata' => [
        'status' => 'active',
        'role' => 'admin',
        'preferences' => [
            'theme' => 'dark',
            'notifications' => 'yes',
        ],
    ],
]);

// Lecture - automatiquement converti en ClusterVO
$cluster = $user->metadata;

// Accès comme un tableau (ArrayAccess)
$status = $cluster['status']; // 'active'
$theme = $cluster['preferences.theme']; // 'dark'

// Accès via get()
$role = $cluster->get('role'); // 'admin'

// Mise à jour
$user->metadata = [
    'status' => 'inactive',
    'role' => 'doctor',
];
$user->save();

// Filtrage Eloquent avec whereCluster
$activeAdmins = User::whereCluster('metadata', 'status=active & role=admin')->get();
```

---

## 7. La collection intelligente : ClusterVOCollection

### 7.1 Création

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

// Création vide
$collection = new ClusterVOCollection();

// Ajout d'éléments
$collection->add(new ClusterVO(['name' => 'John', 'status' => 'active']));
$collection->add(new ClusterVO(['name' => 'Jane', 'status' => 'inactive']));
$collection->add(new ClusterVO(['name' => 'Bob', 'status' => 'active']));
```

### 7.2 Filtres d'égalité

```php
// where - Égalité
$active = $collection->where('status', 'active');
// John, Bob

// whereNot - Différent
$notActive = $collection->whereNot('status', 'active');
// Jane

// whereYes - Égal à 'yes'
$verified = $collection->whereYes('verified');

// whereNo - Égal à 'no'
$unverified = $collection->whereNo('verified');

// whereIn - Dans une liste
$admins = $collection->whereIn('role', ['admin', 'super_admin']);

// whereNotIn - Hors liste
$nonAdmins = $collection->whereNotIn('role', ['admin', 'super_admin']);
```

### 7.3 Filtres numériques

```php
// whereGreaterThan
$adults = $collection->whereGreaterThan('age', 18);

// whereGreaterThanOrEqual
$seniors = $collection->whereGreaterThanOrEqual('age', 65);

// whereLessThan
$minors = $collection->whereLessThan('age', 18);

// whereLessThanOrEqual
$young = $collection->whereLessThanOrEqual('age', 25);

// whereBetween
$middleAged = $collection->whereBetween('age', 35, 50);

// whereNotBetween
$notMiddleAged = $collection->whereNotBetween('age', 35, 50);
```

### 7.4 Filtres d'existence

```php
// whereHas - La clé existe
$hasEmail = $collection->whereHas('email');

// whereMissing - La clé n'existe pas
$noEmail = $collection->whereMissing('email');

// whereNull - La valeur est null
$nullAge = $collection->whereNull('age');

// whereNotNull - La valeur n'est pas null
$hasAge = $collection->whereNotNull('age');
```

### 7.5 Filtres sur chaînes

```php
// whereContains - Contient une sous-chaîne
$containsJohn = $collection->whereContains('name', 'John');

// whereStartsWith - Commence par
$startsJ = $collection->whereStartsWith('name', 'J');

// whereEndsWith - Se termine par
$endsDoe = $collection->whereEndsWith('name', 'Doe');

// whereLike - Alias de whereContains
$likeJohn = $collection->whereLike('name', 'John');

// whereLikePattern - Motif LIKE SQL
$pattern = $collection->whereLikePattern('name', '%john%');  // Contient
$pattern = $collection->whereLikePattern('name', 'john%');   // Commence par
$pattern = $collection->whereLikePattern('name', '%john');    // Se termine par
```

### 7.6 Filtres sur tableaux

```php
// whereArrayContains - Le tableau contient une valeur
$hasPhp = $collection->whereArrayContains('tags', 'php');

// whereArrayNotContains - Le tableau ne contient pas une valeur
$noPhp = $collection->whereArrayNotContains('tags', 'php');

// whereArrayContainsAny - Le tableau contient au moins une valeur
$hasPhpOrJs = $collection->whereArrayContainsAny('tags', ['php', 'js']);

// whereArrayContainsAll - Le tableau contient toutes les valeurs
$hasPhpAndJs = $collection->whereArrayContainsAll('tags', ['php', 'js']);

// whereArraySize - Taille exacte
$exactSize = $collection->whereArraySize('tags', 3);

// whereArraySizeGreaterThan - Taille supérieure
$moreThan2 = $collection->whereArraySizeGreaterThan('tags', 2);

// whereArraySizeLessThan - Taille inférieure
$lessThan2 = $collection->whereArraySizeLessThan('tags', 2);

// whereArrayEmpty - Tableau vide
$emptyTags = $collection->whereArrayEmpty('tags');

// whereArrayNotEmpty - Tableau non vide
$hasTags = $collection->whereArrayNotEmpty('tags');
```

### 7.7 Opérateurs logiques

```php
// AND - via chaînage
$activeAdmins = $collection
    ->where('status', 'active')
    ->where('role', 'admin');

// OR - via orWhere
$adminOrDoctor = $collection
    ->where('role', 'admin')
    ->orWhere('role', 'doctor');

// OR sur condition simple
$activeOrPending = $collection
    ->where('status', 'active')
    ->orWhere('status', 'pending');
```

### 7.8 Filtres personnalisés

```php
// whereClosure - Filtre personnalisé
$complex = $collection->whereClosure(function (ClusterVO $cluster) {
    return $cluster->get('age') > 25 && $cluster->get('role') === 'admin';
});

// orWhereClosure - OR avec filtre personnalisé
$result = $collection
    ->where('status', 'active')
    ->orWhereClosure(function (ClusterVO $cluster) {
        return $cluster->get('age') > 30 && $cluster->get('verified') === 'yes';
    });
```

### 7.9 Requêtes complètes avec whereQuery

```php
// whereQuery - Parse une requête textuelle
$result = $collection->whereQuery('status=active & role=admin');

// Avec OR
$result = $collection->whereQuery('status=active | status=pending');

// Avec parenthèses
$result = $collection->whereQuery('(status=active | status=pending) & role=admin');

// Avec sous-condition
$result = $collection->whereQuery('addresses[city=Kinshasa]');

// Avec fonction SQL
$result = $collection->whereQuery('COUNT(addresses) > 2');
```

### 7.10 Récupération des résultats

```php
// Récupérer tous les éléments
$items = $collection->get();

// Premier élément correspondant
$firstAdmin = $collection->firstWhere('role', 'admin');

// Compter les éléments
$count = $collection->count();

// Itération
foreach ($collection as $cluster) {
    echo $cluster->get('name') . "\n";
}
```

---

## 8. Filtrer des collections en mémoire

### 8.1 Exemple complet

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\ClusterQuery;

$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO([
    'id' => 1,
    'name' => 'John Doe',
    'status' => 'active',
    'role' => 'admin',
    'age' => 30,
    'tags' => ['php', 'js', 'docker'],
    'addresses' => [
        ['city' => 'Kinshasa'],
        ['city' => 'Paris'],
    ],
]));
$clusters->add(new ClusterVO([
    'id' => 2,
    'name' => 'Jane Smith',
    'status' => 'inactive',
    'role' => 'doctor',
    'age' => 25,
    'tags' => ['python', 'react'],
    'addresses' => [
        ['city' => 'Paris'],
    ],
]));
$clusters->add(new ClusterVO([
    'id' => 3,
    'name' => 'Bob Johnson',
    'status' => 'active',
    'role' => 'doctor',
    'age' => 35,
    'tags' => ['php', 'laravel', 'vuejs'],
    'addresses' => [
        ['city' => 'Kinshasa'],
        ['city' => 'London'],
        ['city' => 'Paris'],
    ],
]));

// Filtrage avec ClusterQuery
$engine = new ClusterQuery();
$filtered = $engine->filter($clusters, 'status=active & role=doctor');
// Bob Johnson uniquement

// Filtrage avec ClusterVOCollection
$filtered = $clusters
    ->where('status', 'active')
    ->where('role', 'doctor');
// Bob Johnson uniquement

// Filtrage avec whereQuery
$filtered = $clusters->whereQuery('status=active & role=doctor');
// Bob Johnson uniquement

// Filtrage complexe
$filtered = $clusters->whereQuery(
    'status=active & (role=admin | role=doctor) & COUNT(addresses) > 2'
);
// Bob Johnson uniquement
```

---

## 9. Générer du SQL pour différents drivers

### 9.1 Drivers supportés

```php
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$engine = new ClusterQuery();

// MySQL
$sql = $engine->toSql('clusters', 'status=active', DatabaseDriver::MYSQL);
// JSON_EXTRACT(clusters, '$."status"') = 'active'

// SQLite
$sql = $engine->toSql('clusters', 'status=active', DatabaseDriver::SQLITE);
// json_extract(clusters, '$.status') = 'active'

// PostgreSQL
$sql = $engine->toSql('clusters', 'status=active', DatabaseDriver::PGSQL);
// clusters->>'status' = 'active'
```

### 9.2 Conditions simples

```php
// Égalité
$sql = $engine->toSql('clusters', 'status=active', DatabaseDriver::MYSQL);
// JSON_EXTRACT(clusters, '$."status"') = 'active'

// Différent
$sql = $engine->toSql('clusters', 'status!=inactive', DatabaseDriver::MYSQL);
// JSON_EXTRACT(clusters, '$."status"') != 'inactive'

// Supérieur
$sql = $engine->toSql('clusters', 'age>25', DatabaseDriver::MYSQL);
// CAST(JSON_EXTRACT(clusters, '$."age"') AS DECIMAL(10,2)) > 25

// Inférieur ou égal
$sql = $engine->toSql('clusters', 'age<=25', DatabaseDriver::MYSQL);
// CAST(JSON_EXTRACT(clusters, '$."age"') AS DECIMAL(10,2)) <= 25
```

### 9.3 Conditions avec AND/OR

```php
// AND
$sql = $engine->toSql('clusters', 'status=active & role=admin', DatabaseDriver::MYSQL);
// (JSON_EXTRACT(clusters, '$."status"') = 'active' AND JSON_EXTRACT(clusters, '$."role"') = 'admin')

// OR
$sql = $engine->toSql('clusters', 'status=active | role=admin', DatabaseDriver::MYSQL);
// (JSON_EXTRACT(clusters, '$."status"') = 'active' OR JSON_EXTRACT(clusters, '$."role"') = 'admin')

// Mixte avec parenthèses
$sql = $engine->toSql('clusters', '(status=active | status=pending) & role=admin', DatabaseDriver::MYSQL);
// ((JSON_EXTRACT(clusters, '$."status"') = 'active' OR JSON_EXTRACT(clusters, '$."status"') = 'pending') AND JSON_EXTRACT(clusters, '$."role"') = 'admin')
```

### 9.4 Fonctions SQL

```php
// COUNT
$sql = $engine->toSql('clusters', 'COUNT(addresses) > 2', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses') > 2

// AVG
$sql = $engine->toSql('clusters', 'AVG(scores) >= 85', DatabaseDriver::SQLITE);
// AVG(CAST(json_extract(clusters, '$.scores') AS NUMERIC)) >= 85

// LENGTH
$sql = $engine->toSql('clusters', 'LENGTH(name) > 5', DatabaseDriver::SQLITE);
// LENGTH(json_extract(clusters, '$.name')) > 5

// JSON_LENGTH
$sql = $engine->toSql('clusters', 'JSON_LENGTH(addresses) > 2', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses') > 2
```

### 9.5 Sous-conditions

```php
// Sous-condition simple
$sql = $engine->toSql('clusters', 'addresses[city=Kinshasa]', DatabaseDriver::SQLITE);
// EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE LOWER(json_extract(value, '$.city')) = LOWER('Kinshasa'))

// Sous-condition avec AND
$sql = $engine->toSql('clusters', 'addresses[city=Kinshasa & country=RDC]', DatabaseDriver::SQLITE);
// EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE LOWER(json_extract(value, '$.city')) = LOWER('Kinshasa') AND LOWER(json_extract(value, '$.country')) = LOWER('RDC'))
```

---

## 10. Fonctions SQLite personnalisées

### 10.1 Fonctions disponibles

| Fonction | Description | Exemple |
|----------|-------------|---------|
| `JSON_LENGTH` | Longueur d'un tableau JSON | `JSON_LENGTH(clusters, '$.addresses')` |
| `JSON_AVG` | Moyenne des valeurs numériques | `JSON_AVG(clusters, '$.scores')` |
| `JSON_SUM` | Somme des valeurs numériques | `JSON_SUM(clusters, '$.prices')` |
| `JSON_MIN` | Valeur minimale | `JSON_MIN(clusters, '$.scores')` |
| `JSON_MAX` | Valeur maximale | `JSON_MAX(clusters, '$.scores')` |

### 10.2 Utilisation

```php
// Ces fonctions fonctionnent automatiquement en SQLite
$users = User::whereRaw('JSON_LENGTH(clusters, \'$.addresses\') > 2')->get();

// Ou via whereCluster
$users = User::whereCluster('clusters', 'COUNT(addresses) > 2')->get();
```

---

## 11. Intégration avec Eloquent

### 11.1 Utilisation de base

```php
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

$engine = new ClusterQuery();

$query = User::query();
$engine->applyToEloquent($query, 'clusters', 'status=active', DatabaseDriver::MYSQL);
$users = $query->get();
// SELECT * FROM users WHERE JSON_EXTRACT(clusters, '$."status"') = 'active'
```

### 11.2 Conditions complexes

```php
$query = User::query();

// AND
$engine->applyToEloquent($query, 'clusters', 'status=active & role=admin', DatabaseDriver::MYSQL);

// OR
$engine->applyToEloquent($query, 'clusters', 'status=active | role=admin', DatabaseDriver::MYSQL);

// Parenthèses
$engine->applyToEloquent($query, 'clusters', '(status=active | status=pending) & role=admin', DatabaseDriver::MYSQL);
```

### 11.3 Combinaison avec Eloquent

```php
$users = User::where('created_at', '>', now()->subDays(30))
    ->whereCluster('clusters', 'status=active')
    ->whereCluster('clusters', 'role=admin')
    ->orderBy('name')
    ->get();
```

### 11.4 Sous-conditions Eloquent

```php
$query = User::query();

// Utilisateurs avec une adresse à Kinshasa
$engine->applyToEloquent($query, 'clusters', 'addresses[city=Kinshasa]', DatabaseDriver::MYSQL);

// Utilisateurs avec une adresse à Kinshasa ET actifs
$engine->applyToEloquent($query, 'clusters', 'status=active & addresses[city=Kinshasa]', DatabaseDriver::MYSQL);

$users = $query->get();
```

### 11.5 Fonctions SQL Eloquent

```php
$query = User::query();

// Utilisateurs avec plus de 2 adresses
$engine->applyToEloquent($query, 'clusters', 'COUNT(addresses) > 2', DatabaseDriver::MYSQL);

// Utilisateurs avec une moyenne de scores >= 85
$engine->applyToEloquent($query, 'clusters', 'AVG(scores) >= 85', DatabaseDriver::MYSQL);

// Combinaison
$engine->applyToEloquent($query, 'clusters', 'status=active & COUNT(addresses) > 1', DatabaseDriver::MYSQL);

$users = $query->get();
```

---

## 12. Les Macros Laravel

### 12.1 Macro sur Eloquent Builder

```php
use App\Models\User;

// 1. Condition simple
$users = User::whereCluster('clusters', 'status=active')->get();

// 2. Conditions multiples
$users = User::whereCluster('clusters', 'status=active & role=admin')->get();

// 3. Sous-condition
$users = User::whereCluster('clusters', 'addresses[city=Kinshasa]')->get();

// 4. Fonction SQL
$users = User::whereCluster('clusters', 'COUNT(addresses) > 2')->get();

// 5. Combinaison avec Eloquent
$users = User::where('created_at', '>', now()->subDays(30))
    ->whereCluster('clusters', 'status=active')
    ->orderBy('name')
    ->get();

// 6. Chaînage
$users = User::whereCluster('clusters', 'status=active')
    ->whereCluster('clusters', 'role=admin')
    ->get();
```

### 12.2 Macro sur Collection

```php
use App\Models\User;

$users = User::all();

// 1. Filtrage en mémoire
$active = $users->whereCluster('clusters', 'status=active');

// 2. Chaînage
$admins = $users
    ->whereCluster('clusters', 'status=active')
    ->whereCluster('clusters', 'role=admin');

// 3. Combinaison avec d'autres méthodes
$names = $users
    ->whereCluster('clusters', 'status=active')
    ->pluck('name')
    ->toArray();

// 4. Sous-condition
$kinshasaUsers = $users->whereCluster('clusters', 'addresses[city=Kinshasa]');

// 5. Fonction SQL
$usersWithManyAddresses = $users->whereCluster('clusters', 'COUNT(addresses) > 2');
```

### 12.3 Détection automatique du driver

```php
// Detection automatique (MySQL, PostgreSQL, SQLite)
User::whereCluster('clusters', 'status=active')->get();

// SQL généré selon le driver configuré dans le fichier .env
// DB_CONNECTION=mysql → JSON_EXTRACT
// DB_CONNECTION=pgsql → ->>
// DB_CONNECTION=sqlite → json_extract
```

---

## 13. Les fonctions SQL d'agrégation

### 13.1 Fonctions disponibles

| Fonction | Description | Exemple |
|----------|-------------|---------|
| `COUNT(path)` | Nombre d'éléments dans un tableau | `COUNT(addresses) > 2` |
| `SUM(path)` | Somme des valeurs numériques | `SUM(prices) > 500` |
| `AVG(path)` | Moyenne des valeurs numériques | `AVG(scores) >= 85` |
| `MIN(path)` | Valeur minimale | `MIN(scores) > 75` |
| `MAX(path)` | Valeur maximale | `MAX(scores) < 95` |
| `LENGTH(path)` | Longueur d'une chaîne | `LENGTH(name) > 5` |
| `JSON_LENGTH(path)` | Longueur d'un tableau JSON | `JSON_LENGTH(addresses) > 2` |

### 13.2 Utilisation en mémoire

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John',
    'addresses' => ['a', 'b', 'c'],
    'scores' => [80, 90, 85],
    'prices' => [100, 200, 300],
]));

// COUNT - Compter les éléments
$result = $collection->whereQuery('COUNT(addresses) > 2');

// SUM - Somme des valeurs
$result = $collection->whereQuery('SUM(prices) > 500');

// AVG - Moyenne
$result = $collection->whereQuery('AVG(scores) >= 85');

// MIN - Valeur minimale
$result = $collection->whereQuery('MIN(scores) > 75');

// MAX - Valeur maximale
$result = $collection->whereQuery('MAX(scores) < 95');

// LENGTH - Longueur d'une chaîne
$result = $collection->whereQuery('LENGTH(name) > 5');

// JSON_LENGTH - Longueur d'un tableau JSON
$result = $collection->whereQuery('JSON_LENGTH(addresses) > 2');
```

### 13.3 Utilisation avec Eloquent

```php
use App\Models\User;

// COUNT
$users = User::whereCluster('clusters', 'COUNT(addresses) > 2')->get();

// SUM
$users = User::whereCluster('clusters', 'SUM(prices) > 500')->get();

// AVG
$users = User::whereCluster('clusters', 'AVG(scores) >= 85')->get();

// MIN
$users = User::whereCluster('clusters', 'MIN(scores) > 75')->get();

// MAX
$users = User::whereCluster('clusters', 'MAX(scores) < 95')->get();

// LENGTH
$users = User::whereCluster('clusters', 'LENGTH(name) > 5')->get();

// JSON_LENGTH
$users = User::whereCluster('clusters', 'JSON_LENGTH(addresses) > 2')->get();
```

### 13.4 SQL généré par driver

| Fonction | SQLite | MySQL | PostgreSQL |
|----------|--------|-------|------------|
| `COUNT` | `json_array_length(clusters, '$.addresses')` | `JSON_LENGTH(clusters, '$.addresses')` | `jsonb_array_length(clusters->'addresses')` |
| `SUM` | `(SELECT SUM(json_extract(value, '$')) FROM json_each(clusters, '$.prices'))` | `(SELECT SUM(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(...))` | `(SELECT SUM((value->>'$')::numeric) FROM json_array_elements(...))` |
| `AVG` | `(SELECT AVG(json_extract(value, '$')) FROM json_each(clusters, '$.scores'))` | `(SELECT AVG(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(...))` | `(SELECT AVG((value->>'$')::numeric) FROM json_array_elements(...))` |
| `MIN` | `(SELECT MIN(json_extract(value, '$')) FROM json_each(clusters, '$.scores'))` | `(SELECT MIN(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(...))` | `(SELECT MIN((value->>'$')::numeric) FROM json_array_elements(...))` |
| `MAX` | `(SELECT MAX(json_extract(value, '$')) FROM json_each(clusters, '$.scores'))` | `(SELECT MAX(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(...))` | `(SELECT MAX((value->>'$')::numeric) FROM json_array_elements(...))` |
| `LENGTH` | `LENGTH(json_extract(clusters, '$.name'))` | `LENGTH(JSON_EXTRACT(clusters, '$.name'))` | `LENGTH(clusters->>'name')` |
| `JSON_LENGTH` | `json_array_length(clusters, '$.addresses')` | `JSON_LENGTH(clusters, '$.addresses')` | `jsonb_array_length(clusters->'addresses')` |

---

## 14. Les sous-conditions sur tableaux

### 14.1 Syntaxe

```php
// Syntaxe : path[condition]
$query = 'addresses[city=Kinshasa]';
$query = 'addresses[city=Kinshasa & country=RDC]';
$query = 'addresses[city=Kinshasa | city=Paris]';
$query = 'addresses[city=~kin%]';
$query = 'addresses[#city]'; // NOT_EXISTS
```

### 14.2 Exemples en mémoire

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John',
    'addresses' => [
        ['city' => 'Kinshasa', 'country' => 'RDC'],
        ['city' => 'Paris', 'country' => 'France'],
    ],
]));
$collection->add(new ClusterVO([
    'name' => 'Jane',
    'addresses' => [
        ['city' => 'Paris', 'country' => 'France'],
    ],
]));
$collection->add(new ClusterVO([
    'name' => 'Bob',
    'addresses' => [
        ['city' => 'Kinshasa', 'country' => 'RDC'],
        ['city' => 'London', 'country' => 'UK'],
        ['city' => 'Paris', 'country' => 'France'],
    ],
]));

// Condition simple
$result = $collection->whereQuery('addresses[city=Kinshasa]');
// John, Bob

// Condition avec AND
$result = $collection->whereQuery('addresses[city=Kinshasa & country=RDC]');
// John, Bob

// Condition avec OR
$result = $collection->whereQuery('addresses[city=Kinshasa | city=Paris]');
// John, Jane, Bob

// Condition avec LIKE
$result = $collection->whereQuery('addresses[city=~kin%]');
// John, Bob

// Condition avec NOT_LIKE
$result = $collection->whereQuery('addresses[city!~kin%]');
// Jane, Bob (Bob a Paris et Londres)

// EXISTS - Tableau non vide
$result = $collection->whereQuery('addresses[]');
// John, Jane, Bob

// NOT_EXISTS - Clé absente
$result = $collection->whereQuery('addresses[#city]');
// Personne (tous ont city)
```

### 14.3 Exemples avec Eloquent

```php
use App\Models\User;

// Utilisateurs avec une adresse à Kinshasa
$users = User::whereCluster('clusters', 'addresses[city=Kinshasa]')->get();

// Utilisateurs avec une adresse à Kinshasa ET actifs
$users = User::whereCluster('clusters', 'status=active & addresses[city=Kinshasa]')->get();

// Utilisateurs avec une adresse à Kinshasa ou Paris
$users = User::whereCluster('clusters', 'addresses[city=Kinshasa | city=Paris]')->get();

// Utilisateurs avec au moins une adresse
$users = User::whereCluster('clusters', 'addresses[]')->get();

// Utilisateurs sans adresse
$users = User::whereCluster('clusters', 'addresses[#city]')->get();
```

---

## 15. Les opérateurs EXISTS et NOT_EXISTS

### 15.1 EXISTS (*)

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO(['name' => 'John', 'email' => 'john@example.com']));
$collection->add(new ClusterVO(['name' => 'Jane']));
$collection->add(new ClusterVO(['name' => 'Bob', 'email' => 'bob@example.com']));

// EXISTS
$result = $collection->whereQuery('*email');
// John, Bob

// EXISTS avec condition
$result = $collection->whereQuery('*email & name=John');
// John
```

### 15.2 NOT_EXISTS (#)

```php
// NOT_EXISTS
$result = $collection->whereQuery('#email');
// Jane

// NOT_EXISTS avec condition
$result = $collection->whereQuery('#email & name=Jane');
// Jane
```

### 15.3 Utilisation avec Eloquent

```php
use App\Models\User;

// Utilisateurs avec un email
$users = User::whereCluster('clusters', '*email')->get();

// Utilisateurs sans email
$users = User::whereCluster('clusters', '#email')->get();

// Utilisateurs avec un email et actifs
$users = User::whereCluster('clusters', '*email & status=active')->get();
```

---

## 16. Les opérateurs LIKE et NOT_LIKE

### 16.1 LIKE (=~)

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO(['name' => 'John Doe']));
$collection->add(new ClusterVO(['name' => 'Jane Smith']));
$collection->add(new ClusterVO(['name' => 'Bob Johnson']));

// Contient "John"
$result = $collection->whereQuery('name=~John');
// John Doe, Bob Johnson (contient John)

// Commence par "J"
$result = $collection->whereQuery('name=~J%');
// John Doe, Jane Smith

// Se termine par "n"
$result = $collection->whereQuery('name=~%n');
// Bob Johnson

// Avec motif
$result = $collection->whereQuery('name=~%John%');
// John Doe, Bob Johnson
```

### 16.2 NOT_LIKE (!~)

```php
// Ne contient pas "John"
$result = $collection->whereQuery('name!~John');
// Jane Smith

// Ne commence pas par "J"
$result = $collection->whereQuery('name!~J%');
// Bob Johnson

// Ne se termine pas par "n"
$result = $collection->whereQuery('name!~%n');
// John Doe, Jane Smith
```

### 16.3 Utilisation avec Eloquent

```php
use App\Models\User;

// Noms commençant par "J"
$users = User::whereCluster('clusters', 'name=~J%')->get();

// Noms ne commençant pas par "J"
$users = User::whereCluster('clusters', 'name!~J%')->get();

// Noms contenant "John"
$users = User::whereCluster('clusters', 'name=~%John%')->get();
```

---

## 17. Les fonctions d'agrégation en mémoire

### 17.1 Présentation des fonctions

Les fonctions d'agrégation en mémoire permettent d'effectuer des calculs sur les données des clusters **sans générer de SQL**. Elles sont utilisées avec les méthodes `whereAggregate()`, `matchesAggregate()` et `getAggregateValue()` de `ClusterVOCollection`.

### 17.2 Liste des fonctions d'agrégation

| Fonction | Description | Signature | Retourne |
|----------|-------------|-----------|----------|
| `COUNT` | Compte les éléments d'un tableau ou les caractères d'une chaîne | `COUNT(path)` | `int` |
| `AVG` | Calcule la moyenne des valeurs numériques | `AVG(path)` | `float` |
| `SUM` | Calcule la somme des valeurs numériques | `SUM(path)` | `float` |
| `MIN` | Trouve la valeur numérique minimale | `MIN(path)` | `float` |
| `MAX` | Trouve la valeur numérique maximale | `MAX(path)` | `float` |
| `LENGTH` | Calcule la longueur d'une chaîne ou le nombre d'éléments | `LENGTH(path)` | `int` |
| `EXISTS` | Vérifie qu'un chemin existe et n'est pas vide | `EXISTS(path)` | `bool` |
| `IS_EMPTY` | Détermine si une valeur est vide | `IS_EMPTY(path)` | `bool` |
| `HAS` | Recherche une valeur dans un tableau | `HAS(path, value)` ou `HAS(path, key, value)` | `bool` |
| `ALL` | Vérifie que tous les éléments satisfont une condition | `ALL(path, key, expectedValue)` | `bool` |
| `MATCHES` | Recherche une valeur correspondant à une regex | `MATCHES(path, regex)` ou `MATCHES(path, key, regex)` | `bool` |
| `GROUP` | Groupe des expressions pour la logique booléenne | `GROUP({expr1} & {expr2})` | `bool` |

### 17.3 Utilisation de whereAggregate

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John',
    'addresses' => ['a', 'b', 'c'],
    'scores' => [80, 90, 85],
    'prices' => [100, 200, 300],
    'tags' => ['php', 'javascript', 'docker'],
    'addresses_detail' => [
        ['city' => 'Kinshasa', 'country' => 'RDC'],
        ['city' => 'Paris', 'country' => 'France'],
    ],
    'profile' => ['age' => 30, 'verified' => true],
    'cart' => ['item1', 'item2'],
]));

// COUNT - Compter les éléments
$result = $collection->whereAggregate('{COUNT(addresses) > 2}');

// AVG - Moyenne
$result = $collection->whereAggregate('{AVG(scores) >= 85}');

// SUM - Somme
$result = $collection->whereAggregate('{SUM(prices) > 500}');

// MIN - Valeur minimale
$result = $collection->whereAggregate('{MIN(scores) > 75}');

// MAX - Valeur maximale
$result = $collection->whereAggregate('{MAX(scores) < 95}');

// LENGTH - Longueur
$result = $collection->whereAggregate('{LENGTH(name) > 5}');

// EXISTS - Existence
$result = $collection->whereAggregate('{EXISTS(profile)}');

// IS_EMPTY - Vide
$result = $collection->whereAggregate('{IS_EMPTY(cart)}');

// HAS - Recherche simple
$result = $collection->whereAggregate('{HAS(tags, "php")}');

// HAS - Recherche dans tableau d'objets
$result = $collection->whereAggregate('{HAS(addresses_detail, city, "Kinshasa")}');

// ALL - Tous les éléments
$result = $collection->whereAggregate('{ALL(addresses_detail, country, "RDC")}');

// MATCHES - Regex simple
$result = $collection->whereAggregate('{MATCHES(tags, "/^ja.*/")}');

// MATCHES - Regex sur tableau d'objets
$result = $collection->whereAggregate('{MATCHES(addresses_detail, city, "/^Kin.*/")}');

// GROUP - Combinaison complexe
$result = $collection->whereAggregate(
    '{GROUP({COUNT(addresses) > 1} & {AVG(scores) >= 80} & {HAS(tags, "php")})}'
);
```

### 17.4 Utilisation de whereAggregateDirect

```php
// Exécution directe sans parsing
$result = $collection->whereAggregateDirect('COUNT', ['addresses']);
// Retourne les clusters avec COUNT(addresses) > 0

// Avec opérateur et valeur
$result = $collection->whereAggregateDirect('COUNT', ['addresses'], '>', 2);
// Retourne les clusters avec COUNT(addresses) > 2

$result = $collection->whereAggregateDirect('AVG', ['scores'], '>=', 85);
$result = $collection->whereAggregateDirect('HAS', ['tags', 'php']);
$result = $collection->whereAggregateDirect('EXISTS', ['profile']);
$result = $collection->whereAggregateDirect('MATCHES', ['tags', '/^ja.*/']);
```

### 17.5 Évaluation sur un cluster spécifique

```php
// matchesAggregate - Vérifier si un cluster correspond
$cluster = $collection->first();
$matches = $collection->matchesAggregate($cluster, '{COUNT(addresses) > 2}');

// matchesAggregateDirect - Appel direct
$matches = $collection->matchesAggregateDirect($cluster, 'COUNT', ['addresses']);

// getAggregateValue - Obtenir la valeur
$count = $collection->getAggregateValue($cluster, 'COUNT', ['addresses']);
$avg = $collection->getAggregateValue($cluster, 'AVG', ['scores']);
$hasPhp = $collection->getAggregateValue($cluster, 'HAS', ['tags', 'php']);
$matches = $collection->getAggregateValue($cluster, 'MATCHES', ['tags', '/^ja.*/']);
```

### 17.6 Validation d'expressions

```php
// validateAggregate - Vérifier la syntaxe
$valid = $collection->validateAggregate('{COUNT(addresses) > 2}'); // true
$valid = $collection->validateAggregate('{INVALID(addresses) > 2}'); // false

// Validation avant utilisation
if ($collection->validateAggregate($expression)) {
    $result = $collection->whereAggregate($expression);
} else {
    // Gérer l'erreur
}
```

### 17.7 Exemple complet avec toutes les fonctions

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();

$collection->add(new ClusterVO([
    'name' => 'John',
    'addresses' => ['a', 'b', 'c'],
    'scores' => [80, 90, 85],
    'prices' => [100, 200, 300],
    'tags' => ['php', 'javascript', 'docker'],
    'addresses_detail' => [
        ['city' => 'Kinshasa', 'country' => 'RDC'],
        ['city' => 'Paris', 'country' => 'France'],
    ],
    'profile' => ['age' => 30, 'verified' => true],
    'cart' => ['item1', 'item2'],
]));

$collection->add(new ClusterVO([
    'name' => 'Jane',
    'addresses' => ['a', 'b'],
    'scores' => [70, 75, 80],
    'prices' => [50, 75],
    'tags' => ['python'],
    'addresses_detail' => [
        ['city' => 'Paris', 'country' => 'France'],
    ],
    'profile' => ['age' => 25, 'verified' => false],
    'cart' => [],
]));

$collection->add(new ClusterVO([
    'name' => 'Bob',
    'addresses' => ['a'],
    'scores' => [95, 98, 92],
    'prices' => [500, 600, 700],
    'tags' => ['php', 'laravel', 'vuejs'],
    'addresses_detail' => [
        ['city' => 'Kinshasa', 'country' => 'RDC'],
        ['city' => 'London', 'country' => 'UK'],
        ['city' => 'Paris', 'country' => 'France'],
    ],
    'profile' => ['age' => 35, 'verified' => true],
    'cart' => ['item3'],
]));

// 1. Filtrer avec COUNT
$result = $collection->whereAggregate('{COUNT(addresses) > 2}');
// John, Bob

// 2. Filtrer avec AVG
$result = $collection->whereAggregate('{AVG(scores) >= 85}');
// John, Bob

// 3. Filtrer avec SUM
$result = $collection->whereAggregate('{SUM(prices) > 500}');
// John, Bob

// 4. Filtrer avec MIN
$result = $collection->whereAggregate('{MIN(scores) > 75}');
// John, Bob

// 5. Filtrer avec MAX
$result = $collection->whereAggregate('{MAX(scores) < 95}');
// John, Jane

// 6. Filtrer avec EXISTS
$result = $collection->whereAggregate('{EXISTS(profile)}');
// John, Jane, Bob

// 7. Filtrer avec IS_EMPTY
$result = $collection->whereAggregate('{IS_EMPTY(cart)}');
// Jane

// 8. Filtrer avec HAS
$result = $collection->whereAggregate('{HAS(tags, "php")}');
// John, Bob

// 9. Filtrer avec ALL
$result = $collection->whereAggregate('{ALL(addresses_detail, country, "RDC")}');
// John

// 10. Filtrer avec MATCHES
$result = $collection->whereAggregate('{MATCHES(tags, "/^ja.*/")}');
// John (javascript)

// 11. Combinaison complexe avec GROUP
$result = $collection->whereAggregate(
    '{GROUP({COUNT(addresses) > 1} & {AVG(scores) >= 80} & {HAS(tags, "php")})}'
);
// John, Bob

// 12. Utilisation directe
$result = $collection->whereAggregateDirect('COUNT', ['addresses'], '>', 2);

// 13. Tests individuels
$cluster = $collection->first();
$matches = $collection->matchesAggregate($cluster, '{COUNT(addresses) > 2}');

// 14. Obtention de valeurs
$count = $collection->getAggregateValue($cluster, 'COUNT', ['addresses']);
$avg = $collection->getAggregateValue($cluster, 'AVG', ['scores']);
$hasPhp = $collection->getAggregateValue($cluster, 'HAS', ['tags', 'php']);
$matches = $collection->getAggregateValue($cluster, 'MATCHES', ['tags', '/^ja.*/']);

echo "John: COUNT={$count}, AVG={$avg}, HAS_PHP=" . ($hasPhp ? 'yes' : 'no') . ", MATCHES=" . ($matches ? 'yes' : 'no') . "\n";
// John: COUNT=3, AVG=85, HAS_PHP=yes, MATCHES=yes

// 15. Validation d'expression
$valid = $collection->validateAggregate('{COUNT(addresses) > 2}'); // true
$invalid = $collection->validateAggregate('{INVALID(addresses) > 2}'); // false
```

---

## 18. Créer des fonctions personnalisées

### 18.1 Fonction d'agrégation personnalisée

```php
<?php

namespace App\Cluster\Functions;

use AndyDefer\LaravelCluster\Functions\AbstractAggregateFunction;

class DoubleCountFunction extends AbstractAggregateFunction
{
    public function execute(array $data, array $args): int
    {
        $path = $args[0] ?? null;
        $value = $this->resolveArg($data, $path);

        if (is_array($value)) {
            return count($value) * 2;
        }

        if (is_string($value)) {
            return strlen($value) * 2;
        }

        return 0;
    }

    public function getName(): string
    {
        return 'DOUBLE_COUNT';
    }

    public function getDefaultValue(): mixed
    {
        return 0;
    }

    public function getReturnType(): string
    {
        return 'int';
    }

    public function returnsBoolean(): bool
    {
        return false;
    }

    public function getMinArgs(): int
    {
        return 1;
    }

    public function getMaxArgs(): int
    {
        return 1;
    }

    public function validateArgs(array $args): bool
    {
        return count($args) === 1;
    }
}
```

### 18.2 Enregistrement de la fonction

```php
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;
use App\Cluster\Functions\DoubleCountFunction;

$registry = app(AggregateFunctionRegistry::class);
$registry->register(new DoubleCountFunction());

// Utilisation
$result = $collection->whereAggregate('{DOUBLE_COUNT(addresses) > 4}');
```

### 18.3 Fonction SQL personnalisée

```php
<?php

namespace App\Cluster\SqlFunctions;

use AndyDefer\LaravelCluster\SqlFunctions\AbstractSqlFunction;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

class CustomFunction extends AbstractSqlFunction
{
    public function getName(): string
    {
        return 'CUSTOM';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "CUSTOM_FN(json_extract(%s, '$.%s'))",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "CUSTOM_FN(JSON_EXTRACT(%s, '$.%s'))",
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "CUSTOM_FN(%s->>'%s')",
                $column,
                $path
            ),
        };
    }

    public function getReturnType(): string
    {
        return 'int';
    }

    public function execute(mixed $value): mixed
    {
        // Logique en mémoire
        return is_array($value) ? count($value) * 2 : 0;
    }
}
```

### 18.4 Enregistrement SQL personnalisé

```php
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use App\Cluster\SqlFunctions\CustomFunction;

$registry = app(SqlFunctionRegistry::class);
$registry->register(new CustomFunction());

// Utilisation
$result = $collection->whereAggregate('{CUSTOM(addresses) > 4}');
// ou
$query = User::whereCluster('clusters', 'CUSTOM(addresses) > 4');
```

---

## 19. Parser et AST

### 19.1 Structure de l'AST

```php
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;

$engine = new ClusterQuery();

// ConditionNode - Condition simple
$ast = $engine->parse('status=active');
var_dump($ast instanceof ConditionNode); // true

// GroupNode - Groupe logique (AND/OR)
$ast = $engine->parse('status=active & role=admin');
var_dump($ast instanceof GroupNode); // true

// FunctionNode - Fonction SQL
$ast = $engine->parse('COUNT(addresses) > 2');
var_dump($ast instanceof FunctionNode); // true

// SubConditionNode - Sous-condition
$ast = $engine->parse('addresses[city=Kinshasa]');
var_dump($ast instanceof SubConditionNode); // true
```

### 19.2 Manipulation de l'AST

```php
// ConditionNode
$ast = $engine->parse('status=active');
echo $ast->getKey(); // 'status'
echo $ast->getOperator(); // ComparisonOperator::EQUAL
echo $ast->getValue(); // 'active'

// GroupNode
$ast = $engine->parse('status=active & role=admin');
echo $ast->getOperator(); // LogicalOperator::AND
$children = $ast->getChildren(); // [ConditionNode, ConditionNode]

// FunctionNode
$ast = $engine->parse('COUNT(addresses) > 2');
$children = $ast->getChildren(); // []

// SubConditionNode
$ast = $engine->parse('addresses[city=Kinshasa]');
echo $ast->getPath(); // 'addresses'
$condition = $ast->getCondition(); // ConditionNode

// Évaluation manuelle de l'AST
$cluster = new ClusterVO(['status' => 'active']);
$result = $ast->evaluate($cluster); // true
```

### 19.3 Génération SQL depuis l'AST

```php
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$ast = $engine->parse('status=active');
$sql = $ast->toSql('clusters', DatabaseDriver::MYSQL);
// JSON_EXTRACT(clusters, '$."status"') = 'active'

// Application Eloquent depuis l'AST
$query = User::query();
$ast->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
$users = $query->get();
```

### 19.4 Cache du Parser

```php
$ast1 = $engine->parse('status=active');
$ast2 = $engine->parse('status=active');
// $ast1 et $ast2 sont la même instance

$ast3 = $engine->parse('status=inactive');
// $ast3 est une nouvelle instance
```

---

## 20. Référence des opérateurs

### 20.1 Opérateurs de comparaison

| Opérateur | Description | Exemple |
|-----------|-------------|---------|
| `=` | Égalité | `status=active` |
| `!=` | Différent | `status!=inactive` |
| `<` | Inférieur | `age<18` |
| `>` | Supérieur | `age>18` |
| `<=` | Inférieur ou égal | `age<=18` |
| `>=` | Supérieur ou égal | `age>=18` |
| `=~` | LIKE (insensible à la casse) | `name=~John%` |
| `!~` | NOT LIKE (insensible à la casse) | `name!~John%` |

### 20.2 Opérateurs logiques

| Opérateur | Description | Exemple |
|-----------|-------------|---------|
| `&` ou `AND` | ET logique | `status=active & role=admin` |
| `\|` ou `OR` | OU logique | `status=active | role=admin` |
| `!` ou `NOT` | Négation | `!deleted` |

### 20.3 Opérateurs spéciaux

| Opérateur | Description | Exemple |
|-----------|-------------|---------|
| `*` | EXISTS - La clé existe | `*email` |
| `#` | NOT_EXISTS - La clé est absente | `#deleted_at` |

### 20.4 Parenthèses

```php
// Priorité des opérateurs
$query = '(status=active | status=pending) & role=admin';
// (status=active OU status=pending) ET role=admin
```

---

## 21. Référence des méthodes de ClusterVOCollection

### 21.1 Filtres d'égalité

```php
// where - Égalité simple
$collection->where(string $key, mixed $value): self

// whereNot - Différent
$collection->whereNot(string $key, mixed $value): self

// whereYes - Égal à 'yes'
$collection->whereYes(string $key): self

// whereNo - Égal à 'no'
$collection->whereNo(string $key): self

// orWhere - OR logique
$collection->orWhere(string $key, mixed $value): self

// whereIn - Dans une liste
$collection->whereIn(string $key, array $values): self

// whereNotIn - Hors liste
$collection->whereNotIn(string $key, array $values): self
```

### 21.2 Filtres d'existence

```php
// whereHas - La clé existe
$collection->whereHas(string $key): self

// whereMissing - La clé n'existe pas
$collection->whereMissing(string $key): self

// whereNull - La valeur est null
$collection->whereNull(string $key): self

// whereNotNull - La valeur n'est pas null
$collection->whereNotNull(string $key): self
```

### 21.3 Filtres numériques

```php
// whereGreaterThan
$collection->whereGreaterThan(string $key, int|float $value): self

// whereGreaterThanOrEqual
$collection->whereGreaterThanOrEqual(string $key, int|float $value): self

// whereLessThan
$collection->whereLessThan(string $key, int|float $value): self

// whereLessThanOrEqual
$collection->whereLessThanOrEqual(string $key, int|float $value): self

// whereBetween
$collection->whereBetween(string $key, mixed $min, mixed $max): self

// whereNotBetween
$collection->whereNotBetween(string $key, mixed $min, mixed $max): self
```

### 21.4 Filtres sur chaînes

```php
// whereContains - Contient une sous-chaîne
$collection->whereContains(string $key, string $search): self

// whereStartsWith - Commence par
$collection->whereStartsWith(string $key, string $prefix): self

// whereEndsWith - Se termine par
$collection->whereEndsWith(string $key, string $suffix): self

// whereLike - Alias de whereContains
$collection->whereLike(string $key, string $search): self

// whereLikePattern - Motif LIKE SQL
$collection->whereLikePattern(string $key, string $pattern): self

// whereNotLike - Négation de whereLike
$collection->whereNotLike(string $key, string $search): self

// whereNotLikePattern - Négation de whereLikePattern
$collection->whereNotLikePattern(string $key, string $pattern): self
```

### 21.5 Filtres sur tableaux

```php
// whereArrayContains - Le tableau contient une valeur
$collection->whereArrayContains(string $key, mixed $value): self

// whereArrayNotContains - Le tableau ne contient pas une valeur
$collection->whereArrayNotContains(string $key, mixed $value): self

// whereArrayContainsAny - Le tableau contient au moins une valeur
$collection->whereArrayContainsAny(string $key, array $values): self

// whereArrayContainsAll - Le tableau contient toutes les valeurs
$collection->whereArrayContainsAll(string $key, array $values): self

// whereArraySize - Taille exacte
$collection->whereArraySize(string $key, int $size): self

// whereArraySizeGreaterThan - Taille supérieure
$collection->whereArraySizeGreaterThan(string $key, int $size): self

// whereArraySizeLessThan - Taille inférieure
$collection->whereArraySizeLessThan(string $key, int $size): self

// whereArrayEmpty - Tableau vide
$collection->whereArrayEmpty(string $key): self

// whereArrayNotEmpty - Tableau non vide
$collection->whereArrayNotEmpty(string $key): self
```

### 21.6 Filtres personnalisés

```php
// whereClosure - Filtre personnalisé
$collection->whereClosure(Closure $callback): self

// orWhereClosure - OR avec filtre personnalisé
$collection->orWhereClosure(Closure $callback): self
```

### 21.7 Requêtes textuelles

```php
// whereQuery - Parse une requête textuelle
$collection->whereQuery(string $query): self
```

### 21.8 Agrégations en mémoire

```php
// whereAggregate - Expression d'agrégation
$collection->whereAggregate(string $expression): self

// whereAggregateDirect - Appel direct
$collection->whereAggregateDirect(string $functionName, array $args = []): self
$collection->whereAggregateDirect(string $functionName, array $args, string $operator, mixed $value): self

// matchesAggregate - Vérifier un cluster
$collection->matchesAggregate(ClusterVO $cluster, string $expression): bool

// matchesAggregateDirect - Vérifier un cluster (direct)
$collection->matchesAggregateDirect(ClusterVO $cluster, string $functionName, array $args = []): bool

// getAggregateValue - Obtenir une valeur
$collection->getAggregateValue(ClusterVO $cluster, string $functionName, array $args = []): mixed

// validateAggregate - Valider une expression
$collection->validateAggregate(string $expression): bool
```

### 21.9 Récupération

```php
// get - Tous les éléments
$collection->get(): array

// firstWhere - Premier élément correspondant
$collection->firstWhere(string $key, mixed $value): ?ClusterVO
```

---

## 22. Cas d'usage concrets

### 22.1 Filtrage de clients B2B

```php
<?php

namespace App\Services;

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\Customer;

class CustomerFilterService
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function findCustomers(array $criteria): array
    {
        $conditions = [];

        if (isset($criteria['min_revenue'])) {
            $conditions[] = "revenue >= " . $criteria['min_revenue'];
        }
        if (isset($criteria['industry'])) {
            $conditions[] = "industry=" . $criteria['industry'];
        }
        if (isset($criteria['country'])) {
            $conditions[] = "country=" . $criteria['country'];
        }
        if (isset($criteria['is_active'])) {
            $conditions[] = "active=" . ($criteria['is_active'] ? 'yes' : 'no');
        }
        if (isset($criteria['has_contract'])) {
            $conditions[] = "*contract_signed";
        }

        $queryString = implode(' & ', $conditions);

        $query = Customer::query();
        if (!empty($queryString)) {
            $this->clusterService->applyToEloquent(
                $query,
                'company_data',
                $queryString,
                DatabaseDriver::MYSQL
            );
        }

        return $query->get()->toArray();
    }
}
```

### 22.2 Filtrage de produits e-commerce

```php
<?php

namespace App\Services;

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\Product;

class ProductSearchService
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function searchProducts(array $filters): array
    {
        $query = Product::query();
        $conditions = [];

        // Catégories - avec OR
        if (!empty($filters['categories'])) {
            $categoryConditions = [];
            foreach ($filters['categories'] as $category) {
                $categoryConditions[] = "categories_{$category}=yes";
            }
            $conditions[] = '(' . implode(' OR ', $categoryConditions) . ')';
        }

        // Tags - avec AND
        if (!empty($filters['tags'])) {
            $tagConditions = [];
            foreach ($filters['tags'] as $tag) {
                $tagConditions[] = "tags_{$tag}=yes";
            }
            $conditions[] = '(' . implode(' AND ', $tagConditions) . ')';
        }

        // Prix
        if (isset($filters['min_price'])) {
            $conditions[] = "price >= " . $filters['min_price'];
        }
        if (isset($filters['max_price'])) {
            $conditions[] = "price <= " . $filters['max_price'];
        }

        // Disponibilité
        if (isset($filters['in_stock'])) {
            $conditions[] = "in_stock=" . ($filters['in_stock'] ? 'yes' : 'no');
        }

        // Promotion
        if (isset($filters['on_promotion'])) {
            $conditions[] = "promotion=" . ($filters['on_promotion'] ? 'yes' : 'no');
        }

        if (!empty($conditions)) {
            $queryString = implode(' & ', $conditions);
            $this->clusterService->applyToEloquent(
                $query,
                'product_attributes',
                $queryString,
                DatabaseDriver::MYSQL
            );
        }

        return $query->get()->toArray();
    }
}
```

### 22.3 Filtrage d'utilisateurs avec compétences

```php
<?php

namespace App\Services;

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Services\ClusterService;

class DeveloperFilterService
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function findDevelopers(array $candidates, array $criteria): array
    {
        $collection = new ClusterVOCollection();
        foreach ($candidates as $candidate) {
            $collection->add(new ClusterVO($candidate));
        }

        // Filtrer par compétences
        if (!empty($criteria['required_skills'])) {
            foreach ($criteria['required_skills'] as $skill) {
                $collection = $collection->whereArrayContains('skills', $skill);
            }
        }

        // Filtrer par compétences optionnelles (OR)
        if (!empty($criteria['optional_skills'])) {
            $result = $collection;
            foreach ($criteria['optional_skills'] as $skill) {
                $result = $result->whereArrayContains('skills', $skill);
            }
            // Union des résultats
            $collection = $collection->orWhereQuery(
                '(' . implode(' OR ', array_map(
                    fn($s) => "skills_{$s}=yes",
                    $criteria['optional_skills']
                )) . ')'
            );
        }

        // Années d'expérience
        if (isset($criteria['min_experience'])) {
            $collection = $collection->whereGreaterThanOrEqual('experience', $criteria['min_experience']);
        }

        // Localisation
        if (isset($criteria['city'])) {
            $collection = $collection->where('city', $criteria['city']);
        }

        // Disponibilité
        if (isset($criteria['available'])) {
            $collection = $collection->where('available', $criteria['available'] ? 'yes' : 'no');
        }

        return $collection->get();
    }
}
```

### 22.4 API REST avec filtrage dynamique

```php
<?php

namespace App\Http\Controllers\Api;

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\Resource;

class ResourceController extends Controller
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function index(Request $request)
    {
        $query = Resource::query();
        $filter = $request->get('filter');
        $search = $request->get('search');
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');

        // Filtrage avancé
        if ($filter) {
            $this->clusterService->applyToEloquent(
                $query,
                'metadata',
                $filter,
                DatabaseDriver::MYSQL
            );
        }

        // Recherche textuelle
        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $query->orderBy($sort, $order);

        return $query->paginate(20);
    }
}
```

### 22.5 Filtrage en mémoire pour export

```php
<?php

namespace App\Services;

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

class DataExportService
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function exportFilteredData(array $sourceData, string $filter, array $columns = []): array
    {
        // Conversion en ClusterVOCollection
        $collection = new ClusterVOCollection();
        foreach ($sourceData as $item) {
            $collection->add(new ClusterVO($item));
        }

        // Filtrage
        $filtered = $this->clusterService->filter($collection, $filter);

        // Extraction des colonnes spécifiques
        if (empty($columns)) {
            return $filtered->toArray();
        }

        $result = [];
        foreach ($filtered as $cluster) {
            $row = [];
            foreach ($columns as $column) {
                $row[$column] = $cluster->get($column, null);
            }
            $result[] = $row;
        }

        return $result;
    }
}
```

---

## 23. Débogage et résolution des problèmes

### 23.1 Vérifier la syntaxe d'une requête

```php
use AndyDefer\LaravelCluster\ClusterQuery;

$engine = new ClusterQuery();

try {
    $ast = $engine->parse('status=active & role=admin');
    // Requête valide
} catch (\RuntimeException $e) {
    echo "Erreur de syntaxe: " . $e->getMessage();
}
```

### 23.2 Valider une expression d'agrégation

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;

$collection = new ClusterVOCollection();

$valid = $collection->validateAggregate('{COUNT(addresses) > 2}');
// true

$valid = $collection->validateAggregate('{INVALID(addresses) > 2}');
// false

if (!$valid) {
    // L'expression est invalide
}
```

### 23.3 Afficher le SQL généré

```php
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$engine = new ClusterQuery();

// Afficher le SQL généré pour débogage
$sql = $engine->toSql('clusters', 'status=active & role=admin', DatabaseDriver::MYSQL);
dd($sql); // Voir le SQL exact

// Avec une sous-condition
$sql = $engine->toSql('clusters', 'addresses[city=Kinshasa]', DatabaseDriver::SQLITE);
dd($sql);

// Avec une fonction SQL
$sql = $engine->toSql('clusters', 'COUNT(addresses) > 2', DatabaseDriver::SQLITE);
dd($sql);
```

### 23.4 Tester une requête sur un cluster spécifique

```php
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$engine = new ClusterQuery();

$cluster = new ClusterVO([
    'status' => 'active',
    'role' => 'admin',
    'age' => 30,
]);

// Tester différentes requêtes
$tests = [
    'status=active' => $engine->matches($cluster, 'status=active'),
    'role=admin' => $engine->matches($cluster, 'role=admin'),
    'status=active & role=admin' => $engine->matches($cluster, 'status=active & role=admin'),
    'age>25' => $engine->matches($cluster, 'age>25'),
    'age>35' => $engine->matches($cluster, 'age>35'),
];

foreach ($tests as $query => $result) {
    echo "$query: " . ($result ? '✅ true' : '❌ false') . "\n";
}
```

### 23.5 Problèmes courants

| Problème | Cause | Solution |
|----------|-------|----------|
| Syntax error | Requête mal formée | Vérifier les parenthèses et les opérateurs |
| Valeurs entre guillemets | Guillemets dans la requête | Supprimer les guillemets : `status=active` |
| Fonction inconnue | Fonction non enregistrée | Ajouter la fonction au registre |
| Tableau vide attendu | `whereArrayEmpty` sur tableau non vide | Vérifier la structure du tableau |
| Driver non supporté | Driver inconnu | Utiliser MySQL, PostgreSQL ou SQLite |
| Résultat inattendu | Comportement de l'opérateur | Vérifier la casse des valeurs |

---

## 24. Performance et bonnes pratiques

### 24.1 Performance en mémoire

```php
// ❌ À éviter - Filtrer plusieurs fois
$filtered = $collection->where('status', 'active');
$filtered = $filtered->where('role', 'admin');
$filtered = $filtered->where('age', '>', '25');

// ✅ Recommandé - Chaînage direct
$filtered = $collection
    ->where('status', 'active')
    ->where('role', 'admin')
    ->whereGreaterThan('age', 25);

// ✅ Recommandé - Une seule requête
$filtered = $collection->whereQuery('status=active & role=admin & age>25');
```

### 24.2 Performance en base de données

```php
// ❌ À éviter - Utiliser les fonctions SQL sur des colonnes non indexées
$users = User::whereCluster('clusters', 'COUNT(addresses) > 2')->get();

// ✅ Recommandé - Indexer les colonnes JSON utilisées
// Dans la migration :
DB::statement('ALTER TABLE users ADD INDEX idx_clusters_status ((JSON_EXTRACT(clusters, "$.status")))');

// ✅ Recommandé - Utiliser des conditions simples sur les colonnes indexées
$users = User::whereCluster('clusters', 'status=active')->get();
```

### 24.3 Optimisation des requêtes

```php
// ✅ Recommandé - Limiter les résultats avant de filtrer
$users = User::take(100)
    ->whereCluster('clusters', 'status=active')
    ->get();

// ✅ Recommandé - Utiliser select pour ne récupérer que les colonnes nécessaires
$users = User::select('id', 'name', 'clusters')
    ->whereCluster('clusters', 'status=active')
    ->get();

// ✅ Recommandé - Utiliser pagination
$users = User::whereCluster('clusters', 'status=active')
    ->paginate(20);
```

### 24.4 Bonnes pratiques

```php
// 1. Utiliser les alias pour améliorer la lisibilité
$service = app(ClusterService::class);
$service->applyToEloquent($query, 'metadata', 'status=active', DatabaseDriver::MYSQL);

// 2. Valider les expressions avant de les utiliser
$collection = new ClusterVOCollection();
if ($collection->validateAggregate($expression)) {
    $result = $collection->whereAggregate($expression);
}

// 3. Utiliser les macros Laravel pour plus de clarté
$users = User::whereCluster('clusters', 'status=active')->get();

// 4. Préférer whereQuery pour les requêtes complexes
$result = $collection->whereQuery('status=active & COUNT(addresses) > 2');

// 5. Utiliser les fonctions d'agrégation pour les calculs complexes
$result = $collection->whereAggregate('{AVG(scores) >= 85}');
```

### 24.5 Conservation de la mémoire

```php
// ❌ À éviter - Travailler sur de très grandes collections en mémoire
$allUsers = User::all();
$filtered = $allUsers->whereCluster('clusters', 'status=active');

// ✅ Recommandé - Utiliser le filtrage en base de données
$filtered = User::whereCluster('clusters', 'status=active')->get();

// ✅ Recommandé - Utiliser le streaming si nécessaire
User::whereCluster('clusters', 'status=active')->chunk(100, function ($users) {
    foreach ($users as $user) {
        // Traitement par lots
    }
});
```

---

## 25. Licence

MIT © [Andy Defer](https://github.com/andydefer)