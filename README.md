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
19. [Parser et AST (Arbre Syntaxique Abstrait)](#19-parser-et-ast-arbre-syntaxique-abstrait)
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

Le package est organisé de manière modulaire :

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

Le `ClusterServiceProvider` :

1. Enregistre les services dans le conteneur
2. Enregistre les fonctions SQLite via `SqliteFunctionRegistrar`
3. Enregistre les macros via `ClusterMacroRegistrar`

```php
// ClusterServiceProvider::boot()
public function boot(): void
{
    SqliteFunctionRegistrar::register();    // Fonctions SQLite
    ClusterMacroRegistrar::register();      // Macros Laravel
}
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

Voici comment une requête textuelle est transformée en action concrète :

```php
use AndyDefer\LaravelCluster\Lexer;
use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\ClusterQuery;

// 1. Vous écrivez une requête
$query = 'status=active & age>25 & COUNT(addresses)>2';

// 2. Le Lexer tokenise l'expression
$lexer = new Lexer();
$tokens = $lexer->tokenize($query);
// Tokens: [status, =, active, &, age, >, 25, &, COUNT, (, addresses, ), >, 2]

// 3. Le Parser construit l'AST
$parser = new Parser();
$ast = $parser->parse($query);
// AST: GroupNode(AND) 
//   ├── ConditionNode(status, =, active)
//   ├── ConditionNode(age, >, 25)
//   └── FunctionNode(COUNT, addresses, >, 2)

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
// Retourne une TokenRecordCollection

// 2. Parser - Construit l'AST
$ast = (new Parser())->parse('status=active');
// Retourne un ConditionNode

// 3. ClusterQuery - Moteur central
$engine = new ClusterQuery();
$ast = $engine->parse('status=active');
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

`ClusterService` est une façade qui délègue à `ClusterQuery`. Il est utile pour l'injection de dépendances dans les services Laravel.

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
// ... ajout des clusters ...
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
// ['id', 'name', 'age', 'is_active', 'address.city', 'address.country', ...]

// Récupération des données brutes
$flatData = $cluster->toArray();
$nestedData = $cluster->getUnflattened()->toArray();
```

### 5.3 ArrayAccess (Accès comme un tableau)

`ClusterVO` implémente `ArrayAccess`, ce qui permet d'accéder aux données avec la syntaxe des tableaux :

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

// Le cluster est immutable - les modifications sont bloquées
try {
    $cluster['status'] = 'inactive'; // Lance une RuntimeException
} catch (RuntimeException $e) {
    echo "ClusterVO is immutable";
}
```

### 5.4 Cas d'utilisation

```php
// 1. Dans une collection
$collection = new ClusterVOCollection();
$collection->add($cluster);

// 2. Dans un service
class UserService
{
    public function processUser(array $userData): void
    {
        $cluster = new ClusterVO($userData);

        if ($cluster->get('status') === 'active') {
            // Traitement pour les utilisateurs actifs
        }

        if ($cluster->has('address.city')) {
            $city = $cluster->get('address.city');
            // Traitement selon la ville
        }
    }
}

// 3. Dans une validation
$cluster = new ClusterVO($input);
if ($cluster->get('age') >= 18 && $cluster->get('verified') === 'yes') {
    // Valider l'utilisateur
}
```
## 5.5 Création simplifiée avec ClusterVOProxy

`ClusterVOProxy` est un proxy qui simplifie la création de `ClusterVO` en normalisant automatiquement les valeurs booléennes. Il convertit récursivement les booléens PHP (`true`/`false`) et les chaînes booléennes (`'true'`/`'false'`) en `'yes'`/`'no'`, évitant ainsi les exceptions de `ClusterVO`.

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

// Création avec chaînes booléennes
$cluster = ClusterVOProxy::make([
    'active' => 'true',         // → 'yes'
    'verified' => 'false',      // → 'no'
    'status' => 'active',       // Préservé
]);

// Accès normalisé
$cluster->get('is_active'); // 'yes'
$cluster->get('is_verified'); // 'no'
$cluster->get('settings.notifications.email'); // 'yes'
$cluster->get('settings.notifications.sms'); // 'no'
```

### Avantages du proxy

- **Normalisation automatique** : Plus besoin de convertir manuellement les booléens
- **Support récursif** : Traite les structures profondément imbriquées
- **Préservation des valeurs** : Les chaînes 'yes'/'no' sont conservées
- **Validation intégrée** : Hérite de la validation de `ClusterVO`

### Utilisation dans un modèle

```php
class Doctor extends Model
{
    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVOProxy::make([
            'status' => $this->is_active,
            'verified' => $this->email_verified_at !== null,
            'has_patients' => $this->patients()->exists(),
            'profile' => $this->profile ? [
                'is_verified' => $this->profile->is_verified,
                'is_accepting' => $this->profile->is_accepting_new_patients,
                'years_experience' => $this->profile->years_of_experience,
            ] : null,
            'specialties' => $this->specialties->pluck('name')->toArray(),
        ]);
    }
}
```

### ArrayAccess avec le proxy

```php
$cluster = ClusterVOProxy::make([
    'active' => true,
    'verified' => false,
]);

echo $cluster['active']; // 'yes'
echo $cluster['verified']; // 'no'

// Vérification d'existence
if (isset($cluster['active'])) {
    // La clé existe
}

// Immutable - modification bloquée
try {
    $cluster['active'] = 'inactive';
} catch (RuntimeException $e) {
    echo "ClusterVO is immutable";
}
```
---

## 6. Eloquent Cast : ClusterCast

Le package fournit un cast Eloquent `ClusterCast` qui permet d'utiliser `ClusterVO` directement dans vos modèles Laravel.

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

// Vérification d'existence
if (isset($cluster['preferences.notifications'])) {
    // ...
}

// Mise à jour
$user->metadata = [
    'status' => 'inactive',
    'role' => 'doctor',
];
$user->save();

// Le cast est immutable - pour modifier une valeur spécifique
$data = $user->metadata->toArray();
$data['status'] = 'pending';
$user->metadata = $data;
$user->save();

// Filtrage Eloquent avec whereCluster
$activeAdmins = User::whereCluster('metadata', 'status=active & role=admin')->get();
```

### 6.3 Avantages

- **Transparence** : Les données sont automatiquement converties en ClusterVO
- **ArrayAccess** : Accès natif comme un tableau `$model->metadata['key']`
- **Validation** : Les données sont validées par ClusterVO à l'écriture
- **Compatibilité** : Fonctionne avec toutes les méthodes du package (whereCluster, etc.)

---

## 7. La collection intelligente : ClusterVOCollection

`ClusterVOCollection` offre une API fluide pour filtrer des clusters.

### 7.1 Création d'une collection

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

### 8.2 Chaînage de filtres

```php
// Chaînage avec méthodes de collection
$result = $clusters
    ->where('status', 'active')
    ->whereGreaterThan('age', 25)
    ->whereArrayContains('tags', 'php');

// Chaînage avec whereQuery
$result = $clusters
    ->whereQuery('status=active')
    ->whereQuery('age>25')
    ->whereQuery('tags_php=yes');

// Mélange des deux
$result = $clusters
    ->where('status', 'active')
    ->whereQuery('age>25')
    ->whereArrayContains('tags', 'php');
```

### 8.3 Conservation des clés

```php
// Les clés originales sont conservées
$filtered = $clusters->where('status', 'active');
$filtered->keys(); // [0, 2] (si les indices 0 et 2 correspondent)

// Pour récupérer un tableau indexé normal
$array = $filtered->values()->toArray();
```

---

## 9. Générer du SQL pour différents drivers

### 9.1 Drivers supportés

Le package génère du SQL adapté à chaque driver de base de données :

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

Pour assurer la compatibilité entre les drivers, le package enregistre automatiquement des fonctions SQLite qui imitent les fonctionnalités natives de MySQL et PostgreSQL.

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

### 10.3 Enregistrement

Les fonctions sont enregistrées uniquement si le driver est SQLite, via `SqliteFunctionRegistrar` :

```php
// src/Utilities/SqliteFunctionRegistrar.php
SqliteFunctionRegistrar::register();
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

Le package ajoute automatiquement deux macros : `whereCluster` sur `Builder` et `Collection`.

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

La macro `whereCluster` détecte automatiquement le driver de la connexion :

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
| `COUNT(path)` | Nombre d'éléments | `COUNT(addresses) > 2` |
| `SUM(path)` | Somme des valeurs | `SUM(prices) > 500` |
| `AVG(path)` | Moyenne | `AVG(scores) >= 85` |
| `MIN(path)` | Valeur minimale | `MIN(scores) > 75` |
| `MAX(path)` | Valeur maximale | `MAX(scores) < 95` |
| `LENGTH(path)` | Longueur d'une chaîne | `LENGTH(name) > 5` |
| `JSON_LENGTH(path)` | Longueur d'un tableau JSON | `JSON_LENGTH(addresses) > 2` |

### 13.2 Syntaxe

Les fonctions SQL doivent être entourées d'accolades `{...}` :

```php
// En mémoire (ClusterVOCollection)
$result = $collection->whereAggregate('{COUNT(addresses) > 2}');

// En base de données (Eloquent)
$query->whereCluster('clusters', 'COUNT(addresses) > 2');

// Sans accolades (auto-détection)
$result = $collection->whereQuery('COUNT(addresses) > 2');
```

### 13.3 Exemples en mémoire

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John',
    'addresses' => ['a', 'b', 'c'],
    'scores' => [80, 90, 85],
    'prices' => [100, 200, 300],
    'name' => 'John Doe',
]));

// COUNT - Compter les éléments
$result = $collection->whereAggregate('{COUNT(addresses) > 2}');
// John uniquement

// SUM - Somme des valeurs
$result = $collection->whereAggregate('{SUM(prices) > 500}');
// John uniquement

// AVG - Moyenne
$result = $collection->whereAggregate('{AVG(scores) >= 85}');
// John uniquement

// MIN - Valeur minimale
$result = $collection->whereAggregate('{MIN(scores) > 75}');
// John uniquement

// MAX - Valeur maximale
$result = $collection->whereAggregate('{MAX(scores) < 95}');
// John uniquement

// LENGTH - Longueur d'une chaîne
$result = $collection->whereAggregate('{LENGTH(name) > 5}');
// John Doe (8 caractères)

// JSON_LENGTH - Longueur d'un tableau JSON
$result = $collection->whereAggregate('{JSON_LENGTH(addresses) > 2}');
// John uniquement
```

### 13.4 Exemples en base de données

```php
use App\Models\User;

// COUNT
$users = User::whereCluster('clusters', 'COUNT(addresses) > 2')->get();

// SUM
$users = User::whereCluster('clusters', 'SUM(prices) > 500')->get();

// AVG
$users = User::whereCluster('clusters', 'AVG(scores) >= 85')->get();

// LENGTH
$users = User::whereCluster('clusters', 'LENGTH(name) > 5')->get();

// Combinaison
$users = User::whereCluster('clusters', 'status=active & COUNT(addresses) > 1')->get();
```

### 13.5 Fonctions booléennes

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John',
    'addresses' => ['a', 'b', 'c'],
    'tags' => ['php', 'js', 'docker'],
    'cart' => ['item1', 'item2'],
]));

// EXISTS - Vérification d'existence
$result = $collection->whereAggregate('{EXISTS(addresses)}');
// John (addresses existe)

// HAS - Recherche dans un tableau
$result = $collection->whereAggregate('{HAS(tags, "php")}');
// John (php est dans tags)

// ALL - Tous les éléments satisfont une condition
$result = $collection->whereAggregate('{ALL(addresses, country, "RDC")}');
// Vérifie si toutes les adresses sont en RDC

// IS_EMPTY - Vérification de vacuité
$result = $collection->whereAggregate('{IS_EMPTY(cart)}');
// Vérifie si le panier est vide
```

---

## 14. Les sous-conditions sur tableaux

Les sous-conditions permettent de filtrer sur des tableaux d'objets.

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

### 14.4 Chemins imbriqués

```php
// Structure : settings.notifications.email
$result = $collection->whereQuery('settings.notifications[email=yes]');
// John, Bob (email=yes)

// Structure : settings.notifications[email=yes & sms=no]
$result = $collection->whereQuery('settings.notifications[email=yes & sms=no]');
// John uniquement
```

---

## 15. Les opérateurs EXISTS et NOT_EXISTS

### 15.1 EXISTS (*)

Vérifie si une clé existe dans les données.

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

Vérifie si une clé est absente.

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

Recherche insensible à la casse.

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

Exclusion insensible à la casse.

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

### 17.1 Utilisation de whereAggregate

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John',
    'addresses' => ['a', 'b', 'c'],
    'scores' => [80, 90, 85],
]));

// COUNT
$result = $collection->whereAggregate('{COUNT(addresses) > 2}');

// AVG
$result = $collection->whereAggregate('{AVG(scores) >= 85}');

// Combinaison
$result = $collection->whereAggregate('{COUNT(addresses) > 1} & {AVG(scores) >= 85}');
```

### 17.2 Utilisation de whereAggregateDirect

```php
// Exécution directe sans parsing
$result = $collection->whereAggregateDirect('COUNT', ['addresses']);
// John (3 > 0)

$result = $collection->whereAggregateDirect('EXISTS', ['addresses']);
// John (addresses existe)
```

### 17.3 Évaluation sur un cluster spécifique

```php
// matchesAggregate - Vérifier si un cluster correspond
$cluster = $collection->first();
$matches = $collection->matchesAggregate($cluster, '{COUNT(addresses) > 2}');

// matchesAggregateDirect - Appel direct
$matches = $collection->matchesAggregateDirect($cluster, 'COUNT', ['addresses']);

// getAggregateValue - Obtenir la valeur
$count = $collection->getAggregateValue($cluster, 'COUNT', ['addresses']);
$avg = $collection->getAggregateValue($cluster, 'AVG', ['scores']);
```

### 17.4 Validation d'expressions

```php
// validateAggregate - Vérifier la syntaxe
$valid = $collection->validateAggregate('{COUNT(addresses) > 2}'); // true
$valid = $collection->validateAggregate('{INVALID(addresses) > 2}'); // false
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

## 19. Parser et AST (Arbre Syntaxique Abstrait)

### 19.1 Structure de l'AST

L'AST est composé de différents types de nœuds :

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

Le parser met en cache les résultats pour les requêtes identiques :

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

### 21.8 Agrégations

```php
// whereAggregate - Expression d'agrégation
$collection->whereAggregate(string $expression): self

// whereAggregateDirect - Appel direct
$collection->whereAggregateDirect(string $functionName, array $args = []): self

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

// Utilisation
$service = new CustomerFilterService(app(ClusterService::class));
$customers = $service->findCustomers([
    'min_revenue' => 1000000,
    'industry' => 'technology',
    'country' => 'France',
    'is_active' => true,
    'has_contract' => true,
]);
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

// Utilisation
$service = new ProductSearchService(app(ClusterService::class));
$products = $service->searchProducts([
    'categories' => ['electronics', 'computers'],
    'tags' => ['new', 'best-seller'],
    'min_price' => 500,
    'max_price' => 2000,
    'in_stock' => true,
    'on_promotion' => true,
]);
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

// Utilisation
$service = new DeveloperFilterService(app(ClusterService::class));
$developers = $service->findDevelopers($candidates, [
    'required_skills' => ['php', 'laravel'],
    'optional_skills' => ['docker', 'vuejs'],
    'min_experience' => 3,
    'city' => 'Paris',
    'available' => true,
]);
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

// Exemples d'appels API
// GET /api/resources?filter=status=active AND category=documents
// GET /api/resources?filter=(status=active | status=pending) & tags_php=yes
// GET /api/resources?filter=COUNT(addresses) > 2
// GET /api/resources?search=John&filter=role=admin
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

// Utilisation
$service = new DataExportService(app(ClusterService::class));
$data = $service->exportFilteredData(
    $sourceData,
    'status=active & COUNT(addresses) > 1',
    ['name', 'email', 'status', 'age']
);
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
