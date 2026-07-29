# Laravel Cluster

**Un moteur de requêtes pour données JSON en PHP/Laravel. Parse, filtre, évalue et génère du SQL pour des données structurées.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x%20%7C%2014.x%20%7C%2015.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Pourquoi Laravel Cluster ?](#pourquoi-laravel-cluster-)
3. [Architecture et concepts clés](#architecture-et-concepts-clés)
4. [Le moteur central : ClusterQuery](#le-moteur-central-clusterquery)
5. [Le service façade : ClusterService](#le-service-façade-clusterservice)
6. [La collection intelligente : ClusterVOCollection](#la-collection-intelligente-clustervocollection)
7. [Structure des données : ClusterVO](#structure-des-données-clustervo)
8. [Filtrer des collections en mémoire](#filtrer-des-collections-en-mémoire)
9. [Générer du SQL](#générer-du-sql)
10. [Intégration avec Eloquent](#intégration-avec-eloquent)
11. [Filtrage avancé sur tableaux : cas concrets](#filtrage-avancé-sur-tableaux--cas-concrets)
12. [Cas d'usage concrets](#cas-dusage-concrets)
13. [Référence des opérateurs](#référence-des-opérateurs)

---

## Installation

```bash
composer require andydefer/laravel-cluster
```

**Prérequis :** PHP 8.1+ | Laravel 12.x, 13.x, 14.x ou 15.x

### Service Provider

```php
// config/app.php
'providers' => [
    AndyDefer\LaravelCluster\Providers\ClusterServiceProvider::class,
],
```

### Injection de dépendances

```php
use AndyDefer\LaravelCluster\Services\ClusterService;

class MyService
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}
}
```

---

## Pourquoi Laravel Cluster ?

**Le problème :** Vous avez des données JSON dans votre base de données et vous voulez les filtrer avec des expressions complexes. Les `->whereJsonContains()` ou `->whereRaw()` de Laravel sont limités.

**La solution :** Laravel Cluster. Un moteur de requêtes complet qui :

- Parse des expressions textuelles en arbre syntaxique
- Filtre des données en mémoire
- Gère les tableaux indexés (tags, rôles, etc.)
- Génère du SQL pour MySQL, PostgreSQL et SQLite
- S'intègre avec Eloquent via des paramètres liés
- Supporte les opérateurs `=`, `!=`, `<`, `>`, `<=`, `>=`, `=~` (LIKE), `!~` (NOT LIKE), `*` (EXISTS), `#` (NOT EXISTS)
- Gère les priorités avec parenthèses

---

## Architecture et concepts clés

### Chaîne de traitement

```
Expression textuelle "age > 18 AND status=active"
    ↓
[Lexer] → Tokens [Identifier(age), Operator(>), Identifier(18), Operator(AND), Identifier(status), Operator(=), Identifier(active)]
    ↓
[Parser] → AST (GroupNode AND avec 2 ConditionNode)
    ↓
[ClusterQuery] → Évaluation / SQL / Eloquent
```

### Les composants

| Composant | Rôle |
|-----------|------|
| `Lexer` | Tokenise l'expression en identifiants, opérateurs et parenthèses |
| `Parser` | Construit l'arbre syntaxique (AST) depuis les tokens |
| `ClusterQuery` | Moteur central : parse, filtre, génère du SQL |
| `ClusterService` | Facade pour une API simplifiée |
| `ClusterVO` | Conteneur de données avec aplatissement automatique |
| `ClusterVOCollection` | Collection avec filtrage fluide |

---

## Le moteur central : ClusterQuery

`ClusterQuery` est le cœur du système. Il orchestre toutes les opérations :

### Méthodes principales

| Méthode | Description |
|---------|-------------|
| `parse(string $query): NodeInterface` | Parse une requête en arbre syntaxique (AST) |
| `filter(ClusterVOCollection $clusters, string $query): ClusterVOCollection` | Filtre une collection en mémoire |
| `matches(ClusterVO $cluster, string $query): bool` | Teste si un cluster correspond à la requête |
| `toSql(string $column, string $query, DatabaseDriver $driver): string` | Génère du SQL pour la requête |
| `applyToEloquent(Builder $query, string $column, string $query, DatabaseDriver $driver): void` | Applique la requête à Eloquent |

### Exemple : Utilisation directe

```php
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$engine = new ClusterQuery();

// 1. Parser une requête
$ast = $engine->parse('age > 18 AND status=active');

// 2. Filtrer une collection
$filtered = $engine->filter($clusters, 'score > 80');

// 3. Tester un cluster individuel
$matches = $engine->matches($cluster, 'role=admin');

// 4. Générer du SQL (MySQL)
$sql = $engine->toSql('metadata', 'age > 18', DatabaseDriver::MYSQL);
// "(JSON_EXTRACT(metadata, '$."age"') > '18')"

// 5. Appliquer à Eloquent
$query = User::query();
$engine->applyToEloquent($query, 'settings', 'theme=dark', DatabaseDriver::MYSQL);
```

---

## Le service façade : ClusterService

`ClusterService` est une façade qui délègue toutes les opérations à `ClusterQuery`. Il fournit une API simplifiée pour une utilisation dans les services Laravel.

```php
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$service = new ClusterService(new ClusterQuery());

// Parser une requête
$ast = $service->parse('age > 18 AND status=active');

// Filtrer une collection
$filtered = $service->filter($clusters, 'score > 80');

// Tester un cluster
$matches = $service->matches($cluster, 'role=admin');

// Générer du SQL
$sql = $service->toSql('metadata', 'age > 18', DatabaseDriver::MYSQL);

// Appliquer à Eloquent
$service->applyToEloquent($query, 'settings', 'theme=dark', DatabaseDriver::MYSQL);
```

---

## La collection intelligente : ClusterVOCollection

`ClusterVOCollection` est une collection typée qui offre une API fluide pour filtrer des clusters. Elle maintient le jeu de données original pour supporter les requêtes complexes avec `OR`.

### Exemples d'utilisation

```php
$collection = new ClusterVOCollection();
$collection->add(new ClusterVO(['name' => 'John', 'status' => 'active', 'age' => 25]));

// Filtres d'égalité
$active = $collection->where('status', 'active');
$notActive = $collection->whereNot('status', 'active');
$verified = $collection->whereTrue('verified');

// OR conditions
$activeOrPending = $collection
    ->where('status', 'active')
    ->orWhere('status', 'pending');

// Groupes logiques
$admins = $collection->whereGroup(function ($q) {
    return $q->where('status', 'active')
             ->where('role', 'admin');
});

// Comparaisons numériques
$adults = $collection->whereGreaterThanOrEqual('age', 18);
$youngAdults = $collection->whereBetween('age', 18, 25);

// Recherche textuelle
$johns = $collection->whereContains('name', 'John');
$jNames = $collection->whereStartsWith('name', 'J');

// Filtres personnalisés
$complex = $collection->whereClosure(function ($cluster) {
    return $cluster->get('age') > 25 && $cluster->get('role') === 'admin';
});

// Récupération
$all = $collection->get();
$firstAdmin = $collection->firstWhere('role', 'admin');
```

---

## Structure des données : ClusterVO

`ClusterVO` est le conteneur de données qui aplatit automatiquement les structures imbriquées.

### Exemple

```php
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$cluster = new ClusterVO([
    'id' => 1,
    'name' => 'John Doe',
    'age' => 30,
    'active' => true,
    'address' => [
        'city' => 'Paris',
        'zip' => 75000
    ],
    'tags' => ['php', 'js', 'docker']
]);

// Accès direct (clés aplaties)
$name = $cluster->get('name');                  // 'John Doe'
$city = $cluster->get('address.city');          // 'Paris'
$hasTag = $cluster->get('tags_php');            // 'true'

// Vérification d'existence
if ($cluster->has('address.city')) {
    echo "Ville définie";
}

// Liste des clés disponibles
$keys = $cluster->keys();
// ['id', 'name', 'age', 'active', 'address.city', 'address.zip', 
//  'tags_php', 'tags_js', 'tags_docker']

// Données brutes
$flat = $cluster->toArray();
$original = $cluster->getUnflattened()->toArray();
```

---

## Filtrer des collections en mémoire

```php
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$service = new ClusterService(new ClusterQuery());

// Création d'une collection
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO(['id' => 1, 'name' => 'John', 'age' => 25, 'status' => 'active']));
$clusters->add(new ClusterVO(['id' => 2, 'name' => 'Jane', 'age' => 17, 'status' => 'inactive']));
$clusters->add(new ClusterVO(['id' => 3, 'name' => 'Bob', 'age' => 30, 'status' => 'active']));

// Filtrage
$filtered = $service->filter($clusters, 'age >= 18 AND status=active');
// Résultat : John (25), Bob (30)

// Validation individuelle
$cluster = new ClusterVO(['age' => 25, 'status' => 'active']);
$isMatch = $service->matches($cluster, 'age >= 18 AND status=active');
// true
```

---

## Générer du SQL

```php
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$service = new ClusterService(new ClusterQuery());

// MySQL
$sql = $service->toSql(
    'metadata',
    'age > 18 AND status=active',
    DatabaseDriver::MYSQL
);
// "(JSON_EXTRACT(metadata, '$."age"') > '18' AND JSON_EXTRACT(metadata, '$."status"') = 'active')"

// PostgreSQL
$sql = $service->toSql('metadata', 'age > 18', DatabaseDriver::PGSQL);
// "(metadata->>'age')::numeric > '18'"

// SQLite
$sql = $service->toSql('metadata', 'age > 18', DatabaseDriver::SQLITE);
// "CAST(json_extract(metadata, '$.age') AS INTEGER) > '18'"
```

---

## Intégration avec Eloquent

```php
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

$service = new ClusterService(new ClusterQuery());

$query = User::query();
$service->applyToEloquent(
    $query,
    'settings',
    'preferences.theme=dark AND notifications.enabled=true',
    DatabaseDriver::MYSQL
);

$users = $query->get();
// SELECT * FROM users WHERE (
//   JSON_EXTRACT(settings, '$."preferences.theme"') = 'dark'
//   AND JSON_EXTRACT(settings, '$."notifications.enabled"') = 'true'
// )
```

---

## Filtrage avancé sur tableaux : cas concrets

### Le mécanisme d'aplatissement

Les tableaux sont automatiquement aplatis en clés séparées :

**Donnée originale :**
```json
{
    "tags": ["php", "js", "docker"],
    "roles": ["admin", "editor"]
}
```

**Donnée aplatie :**
```php
[
    'tags_php' => 'true',
    'tags_js' => 'true',
    'tags_docker' => 'true',
    'roles_admin' => 'true',
    'roles_editor' => 'true'
]
```

### Cas concret 1 : Filtrage de développeurs par compétences

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\ClusterQuery;

$service = new ClusterService(new ClusterQuery());

// Base de données des développeurs
$developers = [
    ['name' => 'Alice', 'skills' => ['php', 'js', 'docker']],
    ['name' => 'Bob', 'skills' => ['php', 'python']],
    ['name' => 'Charlie', 'skills' => ['ruby', 'go']],
    ['name' => 'Diana', 'skills' => ['php', 'js', 'rust']],
];

$collection = new ClusterVOCollection();
foreach ($developers as $dev) {
    $collection->add(new ClusterVO($dev));
}

// 1. Trouver les développeurs PHP
$phpDevs = $collection->whereArrayContains('skills', 'php');
// Résultat : Alice, Bob, Diana
echo "Développeurs PHP : " . $phpDevs->count() . PHP_EOL;

// 2. Trouver les développeurs qui ne connaissent pas PHP
$noPhp = $collection->whereArrayNotContains('skills', 'php');
// Résultat : Charlie
echo "Développeurs sans PHP : " . $noPhp->count() . PHP_EOL;

// 3. Trouver les développeurs qui connaissent PHP OU Python
$phpOrPython = $collection->whereArrayContainsAny('skills', ['php', 'python']);
// Résultat : Alice, Bob, Diana
echo "Développeurs PHP ou Python : " . $phpOrPython->count() . PHP_EOL;

// 4. Trouver les développeurs qui connaissent PHP ET JS (full-stack)
$fullStack = $collection->whereArrayContainsAll('skills', ['php', 'js']);
// Résultat : Alice, Diana
echo "Développeurs full-stack (PHP+JS) : " . $fullStack->count() . PHP_EOL;

// 5. Trouver les développeurs avec exactement 3 compétences
$threeSkills = $collection->whereArraySize('skills', 3);
// Résultat : Alice (php, js, docker), Diana (php, js, rust)
echo "Développeurs avec 3 compétences : " . $threeSkills->count() . PHP_EOL;

// 6. Trouver les développeurs avec plus de 2 compétences
$manySkills = $collection->whereArraySizeGreaterThan('skills', 2);
// Résultat : Alice, Diana
echo "Développeurs avec > 2 compétences : " . $manySkills->count() . PHP_EOL;
```

### Cas concret 2 : Filtrage de produits par catégories et tags

```php
// Catalogue de produits
$products = [
    ['name' => 'Laptop', 'categories' => ['electronics', 'computers'], 'price' => 1200],
    ['name' => 'Phone', 'categories' => ['electronics', 'mobile'], 'price' => 800],
    ['name' => 'Book', 'categories' => ['education', 'paper'], 'price' => 25],
    ['name' => 'Tablet', 'categories' => ['electronics', 'computers', 'mobile'], 'price' => 600],
];

$collection = new ClusterVOCollection();
foreach ($products as $product) {
    $collection->add(new ClusterVO($product));
}

// 1. Produits dans la catégorie electronics
$electronics = $collection->whereArrayContains('categories', 'electronics');
// Résultat : Laptop, Phone, Tablet

// 2. Produits dans computers ET mobile
$hybrid = $collection->whereArrayContainsAll('categories', ['computers', 'mobile']);
// Résultat : Tablet (les deux catégories)

// 3. Produits dans electronics OU education
$broad = $collection->whereArrayContainsAny('categories', ['electronics', 'education']);
// Résultat : Laptop, Phone, Book, Tablet (tous sauf... aucun en fait)

// 4. Produits avec plus d'une catégorie
$multiCategory = $collection->whereArraySizeGreaterThan('categories', 1);
// Résultat : Laptop, Phone, Tablet
```

### Cas concret 3 : Filtrer des utilisateurs par rôles et permissions

```php
// Utilisateurs avec rôles et permissions
$users = [
    ['name' => 'John', 'roles' => ['admin', 'editor'], 'permissions' => ['read', 'write', 'delete']],
    ['name' => 'Jane', 'roles' => ['viewer'], 'permissions' => ['read']],
    ['name' => 'Bob', 'roles' => ['editor', 'viewer'], 'permissions' => ['read', 'write']],
    ['name' => 'Alice', 'roles' => ['admin'], 'permissions' => ['read', 'write', 'delete', 'manage']],
];

$collection = new ClusterVOCollection();
foreach ($users as $user) {
    $collection->add(new ClusterVO($user));
}

// 1. Admins avec droit de suppression
$adminDeleters = $collection
    ->whereArrayContains('roles', 'admin')
    ->whereArrayContains('permissions', 'delete');
// Résultat : John, Alice

// 2. Utilisateurs avec au moins 2 rôles
$multiRoles = $collection->whereArraySizeGreaterThan('roles', 1);
// Résultat : John (admin, editor), Bob (editor, viewer)

// 3. Utilisateurs sans rôle admin
$nonAdmins = $collection->whereArrayNotContains('roles', 'admin');
// Résultat : Jane, Bob

// 4. Utilisateurs avec tous les droits (read, write, delete)
$fullAccess = $collection->whereArrayContainsAll('permissions', ['read', 'write', 'delete']);
// Résultat : John, Alice
```

### Cas concret 4 : OR conditions sur les tableaux en base de données

```php
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

$service = new ClusterService(new ClusterQuery());

// Requête Eloquent : utilisateurs actifs OU ceux qui ont le rôle admin
$query = User::query();
$service->applyToEloquent(
    $query,
    'user_data',
    'status=active OR roles_admin=true',
    DatabaseDriver::MYSQL
);
$users = $query->get();

// Requête Eloquent : utilisateurs avec PHP OU Python OU les deux
$query = User::query();
$service->applyToEloquent(
    $query,
    'user_data',
    'skills_php=true OR skills_python=true',
    DatabaseDriver::MYSQL
);
$users = $query->get();
```

### Cas concret 5 : Validation de données avec tableaux

```php
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

class TaskValidator
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function validateTask(array $taskData): bool
    {
        $cluster = new ClusterVO($taskData);

        // Règles de validation
        $rules = [
            // Doit avoir au moins un assigné
            'assignees' => 'size > 0',
            // Ne doit pas avoir plus de 3 tags
            'tags' => 'size <= 3',
            // Doit être dans une catégorie valide
            'categories' => 'contains_any work personal'
        ];

        foreach ($rules as $field => $rule) {
            if (!$this->evaluateRule($cluster, $field, $rule)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateRule(ClusterVO $cluster, string $field, string $rule): bool
    {
        // size > 0, size <= 3, contains_any ...
        if (str_starts_with($rule, 'size > ')) {
            $min = (int) substr($rule, 6);
            return $cluster->get($field . '_count', 0) > $min;
        }

        if (str_starts_with($rule, 'size <= ')) {
            $max = (int) substr($rule, 7);
            return $cluster->get($field . '_count', 0) <= $max;
        }

        if (str_starts_with($rule, 'contains_any ')) {
            $values = explode(' ', substr($rule, 12));
            $query = '(' . implode(' OR ', array_map(
                fn($v) => "{$field}_{$v}=true",
                $values
            )) . ')';
            return $this->clusterService->matches($cluster, $query);
        }

        return true;
    }
}
```

### Cas concret 6 : Recherche avancée avec groupes OR/AND

```php
// Recherche de produits éligibles
$eligibleProducts = $collection->whereGroup(function ($q) {
    // Produits soit en promotion, soit avec un bon prix
    return $q->where('promotion', 'true')
             ->orWhere('price', '<=', '100');
})->whereGroup(function ($q) {
    // Et qui ont soit le tag "best-seller", soit "new"
    return $q->whereArrayContains('tags', 'best-seller')
             ->orWhereArrayContains('tags', 'new');
});

foreach ($eligibleProducts as $product) {
    echo $product->get('name') . PHP_EOL;
}
```

---

## Cas d'usage concrets

### Cas 1 : SaaS - Filtrage des clients par attributs

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
        if (isset($criteria['is_active'])) {
            $conditions[] = "active=" . ($criteria['is_active'] ? 'true' : 'false');
        }

        $queryString = implode(' AND ', $conditions);

        $query = Customer::query();
        $this->clusterService->applyToEloquent(
            $query,
            'company_data',
            $queryString,
            DatabaseDriver::MYSQL
        );

        return $query->get()->toArray();
    }
}

// Utilisation
$service = new CustomerFilterService($clusterService);
$customers = $service->findCustomers([
    'min_revenue' => 100000,
    'industry' => 'technology',
    'is_active' => true
]);
```

### Cas 2 : E-commerce - Filtrage des produits

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
        
        if (!empty($filters['categories'])) {
            $categoryConditions = [];
            foreach ($filters['categories'] as $category) {
                $categoryConditions[] = "categories_{$category}=true";
            }
            $conditions[] = '(' . implode(' OR ', $categoryConditions) . ')';
        }

        if (isset($filters['min_price'])) {
            $conditions[] = "price >= " . $filters['min_price'];
        }
        if (isset($filters['max_price'])) {
            $conditions[] = "price <= " . $filters['max_price'];
        }
        if (isset($filters['in_stock'])) {
            $conditions[] = "in_stock=" . ($filters['in_stock'] ? 'true' : 'false');
        }

        if (!empty($conditions)) {
            $queryString = implode(' AND ', $conditions);
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

### Cas 3 : API REST - Filtrage dynamique

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
        
        if ($filter) {
            $this->clusterService->applyToEloquent(
                $query,
                'metadata',
                $filter,
                DatabaseDriver::MYSQL
            );
        }

        return $query->paginate(20);
    }
}

// GET /api/resources?filter=status=active AND category=documents AND tags_php=true
```

### Cas 4 : Filtrage en mémoire pour export

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

    public function exportFilteredData(array $sourceData, string $filter): array
    {
        $collection = new ClusterVOCollection();
        foreach ($sourceData as $item) {
            $collection->add(new ClusterVO($item));
        }

        $filtered = $this->clusterService->filter($collection, $filter);
        return $filtered->toArray();
    }
}
```

---

## Référence des opérateurs

### Opérateurs de comparaison

| Opérateur | Signification | Exemple |
|-----------|---------------|---------|
| `=` | Égalité | `status=active` |
| `!=` | Différent | `status!=inactive` |
| `<` | Inférieur | `age<18` |
| `>` | Supérieur | `age>18` |
| `<=` | Inférieur ou égal | `age<=18` |
| `>=` | Supérieur ou égal | `age>=18` |
| `=~` | LIKE (correspondance) | `name=~John%` |
| `!~` | NOT LIKE (non-correspondance) | `email!~%@gmail.com` |

### Opérateurs logiques

| Opérateur | Signification | Exemple |
|-----------|---------------|---------|
| `AND` | ET logique | `age>18 AND active=true` |
| `OR` | OU logique | `role=admin OR role=manager` |

### Opérateurs spéciaux

| Opérateur | Signification | Exemple |
|-----------|---------------|---------|
| `*` | EXISTS (clé existe) | `*email` → `email IS NOT NULL` |
| `#` | NOT EXISTS (clé absente) | `#deleted_at` → `deleted_at IS NULL` |
| `!` | NOT (négation) | `!deleted` → `deleted=false` |

### Règles d'écriture des requêtes

**IMPORTANT : Les valeurs ne doivent PAS être entourées de guillemets.**

| ❌ Incorrect | ✅ Correct |
|--------------|-----------|
| `status="active"` | `status=active` |
| `name="John"` | `name=John` |
| `name LIKE "John%"` | `name=~John%` |
| `email NOT LIKE "%@gmail.com"` | `email!~%@gmail.com` |
| `age >= "18"` | `age>=18` |

### Priorité des opérateurs

1. Parenthèses `( )` - priorité maximale
2. Opérateurs unaires (`!`, `*`, `#`)
3. Opérateurs de comparaison (`=`, `!=`, `<`, `>`, etc.)
4. Opérateurs logiques (`AND`, `OR`)

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)