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
4. [Structure des données (ClusterVO)](#structure-des-données-clustervo)
5. [Filtrer des collections en mémoire](#filtrer-des-collections-en-mémoire)
6. [Générer du SQL](#générer-du-sql)
7. [Intégration avec Eloquent](#intégration-avec-eloquent)
8. [Filtrage avancé sur tableaux](#filtrage-avancé-sur-tableaux)
9. [Cas d'usage concrets](#cas-dusage-concrets)
10. [Référence des opérateurs](#référence-des-opérateurs)

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

- ✅ Parse des expressions textuelles en arbre syntaxique
- ✅ Filtre des données en mémoire
- ✅ Gère les tableaux indexés (tags, rôles, etc.)
- ✅ Génère du SQL pour MySQL, PostgreSQL et SQLite
- ✅ S'intègre avec Eloquent via des paramètres liés
- ✅ Supporte les opérateurs `=`, `!=`, `<`, `>`, `<=`, `>=`, `LIKE`, `EXISTS`
- ✅ Gère les priorités avec parenthèses

---

## Architecture et concepts clés

### Chaîne de traitement

```
Expression textuelle "age > 18 AND status = 'active'"
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

## Structure des données (ClusterVO)

`ClusterVO` est le conteneur de données qui aplatit automatiquement les structures imbriquées.

### Création d'un ClusterVO

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
    'tags' => ['php', 'js', 'docker'],
    'preferences' => [
        'theme' => 'dark',
        'notifications' => true
    ]
]);
```

### Accès aux données

```php
// Accès direct (clés aplaties)
$name = $cluster->get('name');                  // 'John Doe'
$city = $cluster->get('address.city');          // 'Paris'
$hasTag = $cluster->get('tags_php');            // 'true'
$isActive = $cluster->get('active');            // 'true'

// Vérification d'existence
if ($cluster->has('address.city')) {
    echo "Ville définie";
}

// Liste des clés disponibles
$keys = $cluster->keys();
// ['id', 'name', 'age', 'active', 'address.city', 'address.zip', 
//  'tags_php', 'tags_js', 'tags_docker', 'preferences.theme', 
//  'preferences.notifications']

// Données brutes (version aplatie)
$flat = $cluster->toArray();

// Données originales (version non-aplatie)
$original = $cluster->getUnflattened()->toArray();
```

### Pourquoi l'aplatissement ?

Les données JSON en base de données sont souvent imbriquées. `ClusterVO` aplatit la structure pour permettre des recherches efficaces :

**Donnée originale :**
```json
{
    "user": {
        "name": "John",
        "preferences": {
            "theme": "dark"
        }
    },
    "tags": ["php", "js"]
}
```

**Donnée aplatie :**
```php
[
    'user.name' => 'John',
    'user.preferences.theme' => 'dark',
    'tags_php' => 'true',
    'tags_js' => 'true'
]
```

Cela permet de rechercher `user.name = "John"` ou `tags_php = "true"` facilement.

---

## Filtrer des collections en mémoire

### Avec ClusterService

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
$filtered = $service->filter($clusters, 'age >= 18 AND status = "active"');

// Résultat : John (25), Bob (30)
foreach ($filtered as $cluster) {
    echo $cluster->get('name') . PHP_EOL;
}
```

### Exemple : Utilisateurs actifs majeurs

```php
$users = [
    new ClusterVO(['id' => 1, 'name' => 'Alice', 'age' => 25, 'active' => true]),
    new ClusterVO(['id' => 2, 'name' => 'Bob', 'age' => 17, 'active' => true]),
    new ClusterVO(['id' => 3, 'name' => 'Charlie', 'age' => 30, 'active' => false]),
    new ClusterVO(['id' => 4, 'name' => 'Diana', 'age' => 22, 'active' => true]),
];

$collection = new ClusterVOCollection();
foreach ($users as $user) {
    $collection->add($user);
}

$adults = $service->filter($collection, 'active = "true" AND age >= 18');
// Résultat : Alice (25), Diana (22)
```

### Exemple : OR avec parenthèses

```php
// Utilisateurs qui sont soit des admins, soit des managers actifs
$filtered = $service->filter(
    $collection,
    'role = "admin" OR (status = "active" AND role = "manager")'
);

foreach ($filtered as $user) {
    echo $user->get('name') . ' - ' . $user->get('role') . PHP_EOL;
}
```

### Validation d'un cluster individuel

```php
$cluster = new ClusterVO(['age' => 25, 'status' => 'active']);

// Vérifier si le cluster correspond
$isMatch = $service->matches($cluster, 'age >= 18 AND status = "active"');
// true

// Vérifier avec une condition différente
$isMatch = $service->matches($cluster, 'role = "admin"');
// false
```

### Exemple : Contrôle d'accès basé sur les attributs

```php
class AccessControl
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function canAccess(User $user, Resource $resource): bool
    {
        $userData = new ClusterVO([
            'id' => $user->id,
            'role' => $user->role,
            'department' => $user->department,
            'permissions' => $user->permissions->toArray()
        ]);

        $condition = 'role = "admin" OR (department = "engineering" AND permissions_edit = "true")';

        return $this->clusterService->matches($userData, $condition);
    }
}

// Utilisation
$access = new AccessControl($service);
if ($access->canAccess($user, $resource)) {
    // Autoriser l'accès
}
```

---

## Générer du SQL

### Exemple de base

```php
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$service = new ClusterService(new ClusterQuery());

// MySQL
$sql = $service->toSql(
    'metadata',
    'age > 18 AND status = "active"',
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

### Exemple : Requête avec LIKE

```php
$sql = $service->toSql(
    'user_data',
    'name LIKE "John%" AND email NOT LIKE "%@gmail.com"',
    DatabaseDriver::MYSQL
);
// "JSON_EXTRACT(user_data, '$."name"') LIKE 'John%' AND JSON_EXTRACT(user_data, '$."email"') NOT LIKE '%@gmail.com'"
```

### Exemple : Requête avec EXISTS et NOT EXISTS

```php
// EXISTS : vérifier qu'un champ existe
$sql = $service->toSql('data', '*email', DatabaseDriver::MYSQL);
// "JSON_EXTRACT(data, '$."email"') IS NOT NULL"

// NOT EXISTS : vérifier qu'un champ n'existe pas
$sql = $service->toSql('data', '#deleted_at', DatabaseDriver::MYSQL);
// "JSON_EXTRACT(data, '$."deleted_at"') IS NULL"
```

---

## Intégration avec Eloquent

### Avec ClusterService

```php
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

$service = new ClusterService(new ClusterQuery());

$query = User::query();
$service->applyToEloquent(
    $query,
    'settings',
    'preferences.theme = "dark" AND notifications.enabled = "true"',
    DatabaseDriver::MYSQL
);

$users = $query->get();
// SELECT * FROM users WHERE (
//   JSON_EXTRACT(settings, '$."preferences.theme"') = 'dark'
//   AND JSON_EXTRACT(settings, '$."notifications.enabled"') = 'true'
// )
```

### Exemple : Filtrage dynamique dans un contrôleur

```php
<?php

namespace App\Http\Controllers;

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

class UserController extends Controller
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function index(Request $request)
    {
        $query = User::query();

        // Construction de la requête à partir des paramètres
        $filters = [];
        if ($request->has('status')) {
            $filters[] = 'status = "' . $request->status . '"';
        }
        if ($request->has('min_age')) {
            $filters[] = 'age >= ' . $request->min_age;
        }
        if ($request->has('role')) {
            $filters[] = 'role = "' . $request->role . '"';
        }

        if (!empty($filters)) {
            $queryString = implode(' AND ', $filters);
            $this->clusterService->applyToEloquent(
                $query,
                'user_data',
                $queryString,
                DatabaseDriver::MYSQL
            );
        }

        return $query->paginate(20);
    }
}
```

### Exemple : Utilisation dans un repository

```php
<?php

namespace App\Repositories;

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

class UserRepository
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function findActiveAdmins(): array
    {
        $query = User::query();
        $this->clusterService->applyToEloquent(
            $query,
            'metadata',
            'status = "active" AND role = "admin"',
            DatabaseDriver::MYSQL
        );
        return $query->get()->toArray();
    }

    public function findUsersWithSkills(array $skills): array
    {
        // Construction de la condition pour les compétences
        $conditions = [];
        foreach ($skills as $skill) {
            $conditions[] = "skills_{$skill} = \"true\"";
        }
        $queryString = implode(' AND ', $conditions);

        $query = User::query();
        $this->clusterService->applyToEloquent(
            $query,
            'user_data',
            $queryString,
            DatabaseDriver::MYSQL
        );
        return $query->get()->toArray();
    }
}

// Utilisation
$repository = new UserRepository($service);
$admins = $repository->findActiveAdmins();
$developers = $repository->findUsersWithSkills(['php', 'js']);
```

---

## Filtrage avancé sur tableaux

### Comment ça fonctionne

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

### Exemples concrets

#### 1. Filtrer par tags

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John',
    'skills' => ['php', 'js', 'docker']
]));
$collection->add(new ClusterVO([
    'name' => 'Jane',
    'skills' => ['php', 'python']
]));
$collection->add(new ClusterVO([
    'name' => 'Bob',
    'skills' => ['ruby', 'go']
]));

// Trouver les développeurs PHP
$phpDevs = $collection->whereArrayContains('skills', 'php');
// Résultat : John, Jane

// Trouver les développeurs qui ne connaissent pas PHP
$noPhp = $collection->whereArrayNotContains('skills', 'php');
// Résultat : Bob

// Trouver les développeurs qui connaissent PHP OU Python
$phpOrPython = $collection->whereArrayContainsAny('skills', ['php', 'python']);
// Résultat : John, Jane

// Trouver les développeurs qui connaissent PHP ET JS
$fullStack = $collection->whereArrayContainsAll('skills', ['php', 'js']);
// Résultat : John
```

#### 2. Filtrer par nombre de compétences

```php
// Développeurs avec exactement 2 compétences
$twoSkills = $collection->whereArraySize('skills', 2);
// Résultat : Jane (php, python)

// Développeurs avec plus de 2 compétences
$manySkills = $collection->whereArraySizeGreaterThan('skills', 2);
// Résultat : John (php, js, docker)

// Développeurs avec moins de 3 compétences
$fewSkills = $collection->whereArraySizeLessThan('skills', 3);
// Résultat : Jane (2), Bob (2)
```

#### 3. Utilisation en base de données avec Eloquent

```php
use App\Models\User;
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$service = new ClusterService(new ClusterQuery());

// Trouver les utilisateurs qui maîtrisent PHP
$query = User::query();
$service->applyToEloquent(
    $query,
    'user_data',
    'skills_php = "true"',
    DatabaseDriver::MYSQL
);
$phpUsers = $query->get();

// Trouver les utilisateurs qui maîtrisent PHP ET JS
$query = User::query();
$service->applyToEloquent(
    $query,
    'user_data',
    'skills_php = "true" AND skills_js = "true"',
    DatabaseDriver::MYSQL
);
$fullStackUsers = $query->get();
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
            $conditions[] = "industry = \"" . $criteria['industry'] . "\"";
        }
        if (isset($criteria['is_active'])) {
            $conditions[] = "active = \"" . ($criteria['is_active'] ? 'true' : 'false') . "\"";
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

        // Construction de la requête
        $conditions = [];
        
        if (!empty($filters['categories'])) {
            $categoryConditions = [];
            foreach ($filters['categories'] as $category) {
                $categoryConditions[] = "categories_{$category} = \"true\"";
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
            $conditions[] = "in_stock = \"" . ($filters['in_stock'] ? 'true' : 'false') . "\"";
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

// Utilisation
$service = new ProductSearchService($clusterService);
$products = $service->searchProducts([
    'categories' => ['electronics', 'gadgets'],
    'min_price' => 100,
    'max_price' => 500,
    'in_stock' => true
]);
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

        // Filtrage dynamique : le client envoie un query string
        // Exemple: ?filter=status="active" AND category="documents"
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

// Requête API
// GET /api/resources?filter=status="active" AND category="documents" AND tags_php="true"
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
        // Convertir les données en clusters
        $collection = new ClusterVOCollection();
        foreach ($sourceData as $item) {
            $collection->add(new ClusterVO($item));
        }

        // Filtrer en mémoire
        $filtered = $this->clusterService->filter($collection, $filter);

        // Exporter
        return $filtered->toArray();
    }
}

// Utilisation
$service = new DataExportService($clusterService);
$exported = $service->exportFilteredData(
    $databaseRecords,
    'department = "sales" AND revenue > 100000 AND active = "true"'
);
```

### Cas 5 : Validation de données complexes

```php
<?php

namespace App\Services;

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

class DataValidator
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function validate(array $data, array $rules): bool
    {
        $cluster = new ClusterVO($data);

        foreach ($rules as $field => $condition) {
            $query = "{$field} {$condition}";
            if (!$this->clusterService->matches($cluster, $query)) {
                return false;
            }
        }

        return true;
    }
}

// Utilisation
$validator = new DataValidator($clusterService);
$isValid = $validator->validate(
    ['age' => 25, 'status' => 'active', 'role' => 'admin'],
    [
        'age' => '>= 18',
        'status' => '= "active"',
        'role' => 'IN ["admin", "manager"]'  // Supporté par whereIn
    ]
);
```

### Cas 6 : Filtrage multi-critères avec groupes logiques

```php
<?php

namespace App\Services;

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\Employee;

class EmployeeSearchService
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function search(array $criteria): array
    {
        $query = Employee::query();
        
        // Construction d'une requête complexe
        // (department = "engineering" OR department = "product") 
        // AND (status = "active" OR status = "probation") 
        // AND salary >= 50000
        
        $conditions = [];
        
        if (!empty($criteria['departments'])) {
            $deptConditions = [];
            foreach ($criteria['departments'] as $dept) {
                $deptConditions[] = "department = \"{$dept}\"";
            }
            $conditions[] = '(' . implode(' OR ', $deptConditions) . ')';
        }
        
        if (!empty($criteria['statuses'])) {
            $statusConditions = [];
            foreach ($criteria['statuses'] as $status) {
                $statusConditions[] = "status = \"{$status}\"";
            }
            $conditions[] = '(' . implode(' OR ', $statusConditions) . ')';
        }
        
        if (isset($criteria['min_salary'])) {
            $conditions[] = "salary >= {$criteria['min_salary']}";
        }
        
        if (!empty($criteria['skills'])) {
            foreach ($criteria['skills'] as $skill) {
                $conditions[] = "skills_{$skill} = \"true\"";
            }
        }

        if (!empty($conditions)) {
            $queryString = implode(' AND ', $conditions);
            $this->clusterService->applyToEloquent(
                $query,
                'employee_data',
                $queryString,
                DatabaseDriver::MYSQL
            );
        }

        return $query->get()->toArray();
    }
}

// Utilisation
$service = new EmployeeSearchService($clusterService);
$employees = $service->search([
    'departments' => ['engineering', 'product'],
    'statuses' => ['active', 'probation'],
    'min_salary' => 50000,
    'skills' => ['php', 'js']
]);
```

---

## Référence des opérateurs

### Opérateurs de comparaison

| Opérateur | Signification | Exemple |
|-----------|---------------|---------|
| `=` | Égalité stricte | `status = "active"` |
| `!=` | Différent | `status != "inactive"` |
| `<` | Inférieur | `age < 18` |
| `>` | Supérieur | `age > 18` |
| `<=` | Inférieur ou égal | `age <= 18` |
| `>=` | Supérieur ou égal | `age >= 18` |
| `LIKE` | Correspondance partielle | `name LIKE "John%"` |
| `NOT LIKE` | Non-correspondance | `email NOT LIKE "%@gmail.com"` |

### Opérateurs logiques

| Opérateur | Signification | Exemple |
|-----------|---------------|---------|
| `AND` | ET logique | `age > 18 AND active = "true"` |
| `OR` | OU logique | `role = "admin" OR role = "manager"` |

### Opérateurs spéciaux

| Opérateur | Signification | Exemple |
|-----------|---------------|---------|
| `*` | EXISTS (clé existe) | `*email` → `email IS NOT NULL` |
| `#` | NOT EXISTS (clé absente) | `#deleted_at` → `deleted_at IS NULL` |
| `!` | NOT (négation) | `!deleted` → `deleted = "false"` |

### Priorité des opérateurs

1. Parenthèses `( )` - priorité maximale
2. Opérateurs unaires (`!`, `*`, `#`)
3. Opérateurs de comparaison (`=`, `!=`, `<`, `>`, etc.)
4. Opérateurs logiques (`AND`, `OR`)

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)