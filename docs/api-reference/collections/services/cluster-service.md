# ClusterService - Référence Technique

## Description

Service façade qui expose les fonctionnalités principales de la bibliothèque de filtrage de clusters. Il agit comme un point d'entrée unique pour le parsing, l'évaluation et la génération de requêtes sur des données ClusterVO.

## Hiérarchie

```
ClusterService
```

**Interfaces :** Aucune (classe finale)

## Rôle principal

`ClusterService` est la porte d'entrée principale du package `laravel-cluster`. Il délègue toutes les opérations à `ClusterQuery` et offre une API simplifiée pour :

- Analyser une expression de requête en arbre syntaxique (`parse`)
- Filtrer une collection de clusters en mémoire (`filter`)
- Tester si un cluster individuel correspond à une requête (`matches`)
- Générer du SQL pour différents moteurs de base de données (`toSql`)
- Appliquer une requête à un constructeur Eloquent (`applyToEloquent`)

Cette classe est conçue pour être injectée comme service dans les applications Laravel ou utilisée directement.

---

## API / Méthodes publiques

### `__construct(ClusterQuery $clusterQuery)`

Initialise le service avec une instance de `ClusterQuery`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$clusterQuery` | `ClusterQuery` | Moteur de requêtes pour les clusters |

**Exemple :**
```php
$service = new ClusterService(new ClusterQuery());
```

---

### `parse(string $query): NodeInterface`

Analyse une expression de requête et retourne l'arbre syntaxique correspondant.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Expression de requête à analyser |

**Retourne :** `NodeInterface` - Racine de l'arbre syntaxique

**Exemple :**
```php
$ast = $service->parse('age > 18 AND status = "active"');
// Retourne un GroupNode avec des ConditionNode enfants
```

---

### `filter(ClusterVOCollection $clusters, string $query): ClusterVOCollection`

Filtre une collection de clusters en mémoire selon l'expression de requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$clusters` | `ClusterVOCollection` | Collection de clusters à filtrer |
| `$query` | `string` | Expression de requête |

**Retourne :** `ClusterVOCollection` - Nouvelle collection contenant uniquement les clusters correspondants

**Exemple :**
```php
$filtered = $service->filter($clusters, 'status = "active" AND age > 18');
foreach ($filtered as $cluster) {
    echo $cluster->get('name');
}
```

---

### `matches(ClusterVO $cluster, string $query): bool`

Teste si un cluster individuel correspond à l'expression de requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Cluster à tester |
| `$query` | `string` | Expression de requête |

**Retourne :** `bool` - `true` si le cluster correspond, `false` sinon

**Exemple :**
```php
$cluster = new ClusterVO(['age' => 25, 'status' => 'active']);
if ($service->matches($cluster, 'age > 18 AND status = "active"')) {
    echo "Le cluster correspond";
}
```

---

### `toSql(string $column, string $query, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

Génère une requête SQL à partir de l'expression de requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | Nom de la colonne JSON à interroger |
| `$query` | `string` | Expression de requête |
| `$driver` | `DatabaseDriver` | Moteur de base de données (MySQL par défaut) |

**Retourne :** `string` - Fragment SQL représentant la condition

**Exemple :**
```php
$sql = $service->toSql('metadata', 'age > 18 AND status = "active"');
// Résultat : "(JSON_EXTRACT(metadata, '$."age"') > '18' AND JSON_EXTRACT(metadata, '$."status"') = 'active')"
```

---

### `applyToEloquent(Builder $query, string $column, string $clusterQuery, DatabaseDriver $driver = DatabaseDriver::MYSQL): void`

Applique l'expression de requête à un constructeur Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Instance du constructeur de requête Eloquent |
| `$column` | `string` | Nom de la colonne JSON |
| `$clusterQuery` | `string` | Expression de requête |
| `$driver` | `DatabaseDriver` | Moteur de base de données (MySQL par défaut) |

**Exemple :**
```php
$query = User::query();
$service->applyToEloquent($query, 'metadata', 'age > 18 AND status = "active"');
$users = $query->get();
// SELECT * FROM users WHERE (JSON_EXTRACT(metadata, '$."age"') > '18' AND JSON_EXTRACT(metadata, '$."status"') = 'active')
```

---

## Cas d'utilisation

### Cas 1 : Filtrage de données en mémoire

Filtrer une collection de clusters avant export CSV.

```php
<?php

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;

$service = new ClusterService(new ClusterQuery());

// Création d'une collection de clusters
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO(['name' => 'John', 'age' => 25, 'status' => 'active']));
$clusters->add(new ClusterVO(['name' => 'Jane', 'age' => 17, 'status' => 'inactive']));
$clusters->add(new ClusterVO(['name' => 'Bob', 'age' => 30, 'status' => 'active']));

// Filtrage
$filtered = $service->filter($clusters, 'age >= 18 AND status = "active"');

// Export des résultats
foreach ($filtered as $cluster) {
    echo $cluster->get('name') . PHP_EOL;
}
// Résultat : John, Bob
```

---

### Cas 2 : Validation individuelle

Vérifier si un cluster spécifique correspond à des critères.

```php
<?php

use AndyDefer\LaravelCluster\Services\ClusterService;

$service = new ClusterService(new ClusterQuery());

$cluster = new ClusterVO([
    'user_id' => 123,
    'email' => 'john@example.com',
    'role' => 'admin',
    'active' => true
]);

$query = 'role = "admin" AND active = "true"';

if ($service->matches($cluster, $query)) {
    echo "L'utilisateur est un administrateur actif";
}
```

---

### Cas 3 : Intégration avec Eloquent

Filtrer les utilisateurs d'une base de données avec des critères complexes.

```php
<?php

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

$service = new ClusterService(new ClusterQuery());

$query = User::query();
$service->applyToEloquent(
    $query,
    'settings', // colonne JSON
    'preferences.theme = "dark" AND notifications.enabled = "true"',
    DatabaseDriver::MYSQL
);

$users = $query->get();
// SELECT * FROM users WHERE (JSON_EXTRACT(settings, '$."preferences.theme"') = 'dark' AND JSON_EXTRACT(settings, '$."notifications.enabled"') = 'true')
```

---

### Cas 4 : Génération de rapports

Générer un rapport avec des filtres dynamiques.

```php
<?php

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

class ReportGenerator
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function generateReport(string $filter, array $data): array
    {
        // Parser la requête pour validation
        $ast = $this->clusterService->parse($filter);

        // Filtrer les données
        $clusters = new ClusterVOCollection();
        foreach ($data as $item) {
            $clusters->add(new ClusterVO($item));
        }

        $filtered = $this->clusterService->filter($clusters, $filter);

        return $filtered->get();
    }
}

// Utilisation
$reportGenerator = new ReportGenerator($service);
$report = $reportGenerator->generateReport(
    'department = "sales" AND revenue > 1000',
    $rawData
);
```

---

### Cas 5 : API REST avec filtrage dynamique

Exposer un endpoint de recherche avec filtres.

```php
<?php

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\Product;

class ProductController
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function search(Request $request)
    {
        $filters = $request->get('filters', '');

        $query = Product::query();
        $this->clusterService->applyToEloquent(
            $query,
            'attributes', // colonne JSON
            $filters,
            DatabaseDriver::MYSQL
        );

        return $query->paginate(20);
    }
}

// Requête : /api/products?filters=category="electronics" AND price > 100 AND in_stock="true"
```

---

## Gestion des erreurs

La classe `ClusterService` ne lève pas directement d'exceptions. Elle délègue les opérations à `ClusterQuery` qui peut lever les exceptions suivantes :

| Situation | Exception | Message |
|-----------|-----------|---------|
| Syntaxe de requête invalide | `InvalidArgumentException` | Message décrivant l'erreur de parsing |
| Token inconnu | `InvalidArgumentException` | Message décrivant le token inattendu |
| Opérateur non supporté | `InvalidArgumentException` | `Unsupported operator: {operator}` |
| Clé JSON invalide | `InvalidArgumentException` | `Invalid JSON key: {key}` |

### Bonnes pratiques

Pour une utilisation robuste :

```php
use AndyDefer\LaravelCluster\Services\ClusterService;

try {
    $filtered = $service->filter($clusters, $userInput);
} catch (InvalidArgumentException $e) {
    // Gérer l'erreur de syntaxe
    return response()->json(['error' => 'Invalid filter syntax'], 400);
}
```

---

## Intégration

`ClusterService` s'intègre avec :

- **`ClusterQuery`** : Moteur de requêtes sous-jacent
- **`NodeInterface`** : Interface des nœuds syntaxiques
- **`ClusterVOCollection`** : Collection de clusters à filtrer
- **`ClusterVO`** : Objet de données évalué
- **`DatabaseDriver`** : Énumération des moteurs de bases de données
- **Eloquent `Builder`** : Construction de requêtes

### Injection de dépendance

Dans Laravel, le service peut être enregistré dans le conteneur :

```php
// Dans AppServiceProvider
$this->app->singleton(ClusterService::class, function ($app) {
    return new ClusterService(new ClusterQuery());
});

// Utilisation
$service = app(ClusterService::class);
```

---

## Performance

### Complexité algorithmique

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `parse()` | O(n) | n = longueur de la requête |
| `filter()` | O(n * m) | n = clusters, m = nœuds de l'AST |
| `matches()` | O(m) | m = nœuds de l'AST |
| `toSql()` | O(m) | m = nœuds de l'AST |
| `applyToEloquent()` | O(m) | m = nœuds de l'AST |

### Optimisations

- L'AST est calculé une seule fois lors du parsing
- Les résultats de parsing peuvent être mis en cache si la même requête est utilisée plusieurs fois
- Court-circuit pour les opérateurs logiques (`AND`, `OR`)
- Utilisation de paramètres liés pour les requêtes Eloquent

### Cache recommandé

```php
use Illuminate\Support\Facades\Cache;

class CachedClusterService
{
    public function __construct(
        private readonly ClusterService $service
    ) {}

    public function parse(string $query): NodeInterface
    {
        $key = 'ast_' . md5($query);
        return Cache::remember($key, 3600, function () use ($query) {
            return $this->service->parse($query);
        });
    }
}
```

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (nécessite PHP 8.0+) |

**Dépendances Laravel :**

| Version Laravel | Support |
|-----------------|---------|
| Laravel 11.x | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 9.x | ✅ Complet |
| Laravel 8.x | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

// 1. Instanciation du service
$service = new ClusterService(new ClusterQuery());

// 2. Parsing d'une requête
$ast = $service->parse('age > 18 AND status = "active"');
echo "AST parsé avec succès" . PHP_EOL;

// 3. Création de données de test
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO([
    'id' => 1,
    'name' => 'John Doe',
    'age' => 25,
    'status' => 'active',
    'role' => 'admin'
]));
$clusters->add(new ClusterVO([
    'id' => 2,
    'name' => 'Jane Smith',
    'age' => 17,
    'status' => 'inactive',
    'role' => 'user'
]));
$clusters->add(new ClusterVO([
    'id' => 3,
    'name' => 'Bob Johnson',
    'age' => 30,
    'status' => 'active',
    'role' => 'manager'
]));

// 4. Filtrage en mémoire
$filtered = $service->filter($clusters, 'age >= 18 AND (status = "active" OR role = "manager")');
echo "Clusters filtrés : " . count($filtered) . PHP_EOL;
// Résultat : John Doe (25, active), Bob Johnson (30, manager)

// 5. Test d'un cluster individuel
$cluster = new ClusterVO(['age' => 25, 'status' => 'active']);
$matches = $service->matches($cluster, 'age > 18 AND status = "active"');
echo $matches ? 'Correspond' : 'Ne correspond pas' . PHP_EOL;
// "Correspond"

// 6. Génération de SQL
$sql = $service->toSql(
    'data',
    'age > 18 AND status = "active"',
    DatabaseDriver::MYSQL
);
echo "SQL généré : " . $sql . PHP_EOL;

// 7. Application à Eloquent
$query = User::query();
$service->applyToEloquent(
    $query,
    'metadata',
    'preferences.theme = "dark" AND notifications.enabled = "true"',
    DatabaseDriver::MYSQL
);
$users = $query->get();
echo "Utilisateurs trouvés : " . $users->count() . PHP_EOL;

// 8. Utilisation avec gestion d'erreurs
try {
    $result = $service->filter($clusters, 'age > INVALID');
} catch (InvalidArgumentException $e) {
    echo "Erreur de syntaxe : " . $e->getMessage() . PHP_EOL;
}

// 9. Requête complexe avec groupes imbriqués
$complexQuery = '(role = "admin" OR role = "manager") AND (status = "active" OR verified = "true")';
$complexSql = $service->toSql('data', $complexQuery, DatabaseDriver::MYSQL);
echo "Requête complexe : " . $complexSql . PHP_EOL;

// 10. Intégration dans un service Laravel
class UserFilterService
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function findUsers(string $filter): array
    {
        $query = User::query();
        $this->clusterService->applyToEloquent(
            $query,
            'user_data',
            $filter,
            DatabaseDriver::MYSQL
        );
        return $query->get()->toArray();
    }
}

// Utilisation du service
$userFilter = new UserFilterService($service);
$users = $userFilter->findUsers('role = "admin" AND active = "true"');
```

---

## Voir aussi

- `ClusterQuery` - Moteur de requêtes sous-jacent
- `ClusterVOCollection` - Collection de clusters
- `ClusterVO` - Objet de données
- `NodeInterface` - Interface des nœuds syntaxiques
- `ConditionNode` - Nœud conditionnel atomique
- `GroupNode` - Nœud logique composite
- `DatabaseDriver` - Énumération des moteurs de bases de données