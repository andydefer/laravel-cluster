# ClusterQuery - Référence Technique

## Description

Moteur de requêtes pour les clusters qui orchestre l'analyse syntaxique, l'évaluation et la génération de requêtes SQL. Il sert de pont entre les expressions de requête textuelles et les opérations sur les données.

## Hiérarchie

```
ClusterQuery
```

**Interfaces :** Aucune (classe finale)

## Rôle principal

`ClusterQuery` est le moteur central du package. Il coordonne toutes les opérations liées aux requêtes :

- **Parsing** : Transformation d'une expression textuelle en arbre syntaxique
- **Filtrage mémoire** : Évaluation des conditions sur des collections de clusters
- **Validation individuelle** : Test d'un cluster unique contre une requête
- **Génération SQL** : Production de fragments SQL pour différents moteurs de bases de données
- **Intégration Eloquent** : Application des conditions à des requêtes de base de données

Le moteur est conçu pour être flexible et extensible, avec un mécanisme de parsing pluggable.

---

## API / Méthodes publiques

### `__construct(?ParserInterface $parser = null)`

Initialise le moteur de requêtes avec un parseur optionnel.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parser` | `ParserInterface` | Parseur personnalisé (par défaut : `Parser`) |

**Exemple :**
```php
// Avec parseur par défaut
$engine = new ClusterQuery();

// Avec parseur personnalisé
$engine = new ClusterQuery(new CustomParser());
```

---

### `parse(string $query): NodeInterface`

Analyse une expression de requête et retourne l'arbre syntaxique correspondant.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Expression de requête à analyser |

**Retourne :** `NodeInterface` - Racine de l'arbre syntaxique

**Exceptions :** `InvalidArgumentException` - Si la syntaxe est invalide

**Exemple :**
```php
$ast = $engine->parse('age > 18 AND status = "active"');
// Retourne un GroupNode(AND) avec deux ConditionNode
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
$filtered = $engine->filter($clusters, 'status = "active" AND age > 18');
echo "Résultats : " . $filtered->count();
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
if ($engine->matches($cluster, 'age > 18 AND status = "active"')) {
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
$sql = $engine->toSql(
    'metadata',
    'age > 18 AND status = "active"',
    DatabaseDriver::MYSQL
);
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
$engine->applyToEloquent(
    $query,
    'settings',
    'preferences.theme = "dark" AND notifications.enabled = "true"',
    DatabaseDriver::MYSQL
);
$users = $query->get();
```

---

## Cas d'utilisation

### Cas 1 : Filtrage de données en mémoire

Filtrer une collection de résultats avant export.

```php
<?php

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$engine = new ClusterQuery();

// Préparation des données
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO(['id' => 1, 'name' => 'John', 'age' => 25, 'status' => 'active']));
$clusters->add(new ClusterVO(['id' => 2, 'name' => 'Jane', 'age' => 17, 'status' => 'inactive']));
$clusters->add(new ClusterVO(['id' => 3, 'name' => 'Bob', 'age' => 30, 'status' => 'active']));

// Filtrage
$filtered = $engine->filter(
    $clusters,
    'age >= 18 AND status = "active"'
);

// Export CSV
foreach ($filtered as $cluster) {
    echo $cluster->get('name') . ',' . $cluster->get('age') . PHP_EOL;
}
// Résultat : John,25 / Bob,30
```

---

### Cas 2 : Validation individuelle

Vérifier si un cluster spécifique correspond à des critères métier.

```php
<?php

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$engine = new ClusterQuery();

$order = new ClusterVO([
    'amount' => 150.00,
    'status' => 'paid',
    'customer' => [
        'vip' => true,
        'country' => 'FR'
    ]
]);

$query = 'amount > 100 AND status = "paid" AND customer.vip = "true"';

if ($engine->matches($order, $query)) {
    echo "La commande est éligible pour la livraison express";
}
```

---

### Cas 3 : Génération de requêtes SQL

Générer des requêtes pour différents moteurs de bases de données.

```php
<?php

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$engine = new ClusterQuery();
$query = 'age > 18 AND status = "active" AND tags_php = "true"';

// Pour MySQL
$sql = $engine->toSql('data', $query, DatabaseDriver::MYSQL);
echo "MySQL : " . $sql . PHP_EOL;

// Pour PostgreSQL
$sql = $engine->toSql('data', $query, DatabaseDriver::PGSQL);
echo "PostgreSQL : " . $sql . PHP_EOL;

// Pour SQLite
$sql = $engine->toSql('data', $query, DatabaseDriver::SQLITE);
echo "SQLite : " . $sql . PHP_EOL;
```

---

### Cas 4 : Intégration avec Laravel Eloquent

Filtrer des modèles Eloquent avec des expressions complexes.

```php
<?php

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

class UserRepository
{
    public function __construct(
        private readonly ClusterQuery $engine
    ) {}

    public function findUsers(array $filters): array
    {
        $query = User::query();

        // Construction de la requête à partir des filtres
        $conditions = [];
        foreach ($filters as $key => $value) {
            $conditions[] = "{$key} = \"{$value}\"";
        }
        $clusterQuery = implode(' AND ', $conditions);

        $this->engine->applyToEloquent(
            $query,
            'metadata', // colonne JSON
            $clusterQuery,
            DatabaseDriver::MYSQL
        );

        return $query->get()->toArray();
    }
}

// Utilisation
$repository = new UserRepository(new ClusterQuery());
$users = $repository->findUsers([
    'status' => 'active',
    'role' => 'admin'
]);
```

---

### Cas 5 : API REST dynamique

Exposer une API avec filtrage dynamique.

```php
<?php

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\Product;

class ProductController
{
    public function __construct(
        private readonly ClusterQuery $engine
    ) {}

    public function index(Request $request)
    {
        $filter = $request->get('filter', '');
        $orderBy = $request->get('order_by', 'id');
        $direction = $request->get('direction', 'asc');

        $query = Product::query();

        if (!empty($filter)) {
            $this->engine->applyToEloquent(
                $query,
                'attributes',
                $filter,
                DatabaseDriver::MYSQL
            );
        }

        return $query->orderBy($orderBy, $direction)->paginate(20);
    }
}

// Requête : /api/products?filter=category="electronics" AND price > 100&order_by=price&direction=desc
```

---

### Cas 6 : Cache des arbres syntaxiques

Optimiser les performances en mettant en cache les expressions analysées.

```php
<?php

use AndyDefer\LaravelCluster\ClusterQuery;
use Illuminate\Support\Facades\Cache;

class CachedClusterQuery extends ClusterQuery
{
    public function parse(string $query): NodeInterface
    {
        $key = 'ast_' . md5($query);

        return Cache::remember($key, 3600, function () use ($query) {
            return parent::parse($query);
        });
    }
}

// Utilisation
$engine = new CachedClusterQuery();
$ast = $engine->parse('age > 18 AND status = "active"'); // Pas de cache
$ast = $engine->parse('age > 18 AND status = "active"'); // Cache hit
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Syntaxe de requête invalide | `InvalidArgumentException` | Message décrivant l'erreur de parsing |
| Token inconnu | `InvalidArgumentException` | `Unexpected token "{token}" at position {position}` |
| Parenthèses non équilibrées | `InvalidArgumentException` | `Unbalanced parentheses` |
| Opérateur manquant | `InvalidArgumentException` | `Expected operator between expressions` |
| Valeur manquante | `InvalidArgumentException` | `Expected value after operator` |
| Type de valeur invalide | `InvalidArgumentException` | Message décrivant le type attendu |

### Bonnes pratiques

```php
use AndyDefer\LaravelCluster\ClusterQuery;

$engine = new ClusterQuery();

try {
    $filtered = $engine->filter($clusters, $userInput);
} catch (InvalidArgumentException $e) {
    // Journalisation
    Log::warning('Invalid filter syntax', [
        'query' => $userInput,
        'error' => $e->getMessage()
    ]);

    // Réponse utilisateur
    return response()->json([
        'error' => 'Invalid filter syntax',
        'message' => $e->getMessage()
    ], 400);
}
```

---

## Intégration

`ClusterQuery` s'intègre avec :

- **`ParserInterface`** : Interface du parseur (pluggable)
- **`Parser`** : Parseur par défaut
- **`NodeInterface`** : Interface des nœuds syntaxiques
- **`ClusterVOCollection`** : Collection de clusters
- **`ClusterVO`** : Objet de données
- **`DatabaseDriver`** : Énumération des moteurs de bases de données
- **Eloquent `Builder`** : Construction de requêtes

### Extension

Le parseur peut être remplacé par une implémentation personnalisée :

```php
interface ParserInterface
{
    public function parse(string $query): NodeInterface;
}

class CustomParser implements ParserInterface
{
    public function parse(string $query): NodeInterface
    {
        // Logique de parsing personnalisée
    }
}

$engine = new ClusterQuery(new CustomParser());
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
- Court-circuit pour les opérateurs logiques (`AND`, `OR`)
- Utilisation de paramètres liés pour les requêtes Eloquent
- Parsing sans allocations mémoire superflues

### Recommandations

Pour de grandes collections (> 10 000 éléments) :
1. Utilisez `applyToEloquent()` pour un filtrage au niveau de la base de données
2. Mettez en cache les expressions fréquemment utilisées
3. Évitez le filtrage mémoire pour de très gros volumes

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

**Moteurs de bases de données supportés :**

| Base de données | Support SQL | Support Eloquent |
|-----------------|-------------|------------------|
| MySQL | ✅ Complet | ✅ Complet |
| PostgreSQL | ✅ Complet | ✅ Complet |
| SQLite | ✅ Complet | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

// 1. Instanciation
$engine = new ClusterQuery();

// 2. Parsing d'une requête
$ast = $engine->parse('age > 18 AND (status = "active" OR role = "admin")');
echo "AST parsé avec succès" . PHP_EOL;

// 3. Création des données de test
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO([
    'id' => 1,
    'name' => 'John Doe',
    'age' => 25,
    'status' => 'active',
    'role' => 'user'
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
    'role' => 'admin'
]));
$clusters->add(new ClusterVO([
    'id' => 4,
    'name' => 'Alice Brown',
    'age' => 22,
    'status' => 'pending',
    'role' => 'guest'
]));

// 4. Filtrage en mémoire
$filtered = $engine->filter(
    $clusters,
    'age >= 18 AND (status = "active" OR role = "admin")'
);

echo "Clusters filtrés : " . $filtered->count() . PHP_EOL;
// Résultat : 2 (John Doe, Bob Johnson)

foreach ($filtered as $cluster) {
    echo "- " . $cluster->get('name') . " (âge: " . $cluster->get('age') . ")\n";
}

// 5. Test d'un cluster individuel
$testCluster = new ClusterVO(['age' => 25, 'status' => 'active']);
$matches = $engine->matches($testCluster, 'age >= 18 AND status = "active"');
echo $matches ? "\nLe cluster correspond" : "\nLe cluster ne correspond pas";
echo PHP_EOL;

// 6. Génération de SQL MySQL
$sql = $engine->toSql(
    'data',
    'age > 18 AND status = "active"',
    DatabaseDriver::MYSQL
);
echo "\nSQL MySQL généré :\n" . $sql . PHP_EOL;

// 7. Génération de SQL PostgreSQL
$sql = $engine->toSql(
    'data',
    'age > 18 AND status = "active"',
    DatabaseDriver::PGSQL
);
echo "\nSQL PostgreSQL généré :\n" . $sql . PHP_EOL;

// 8. Génération de SQL SQLite
$sql = $engine->toSql(
    'data',
    'age > 18 AND status = "active"',
    DatabaseDriver::SQLITE
);
echo "\nSQL SQLite généré :\n" . $sql . PHP_EOL;

// 9. Application à Eloquent
$query = User::query();
$engine->applyToEloquent(
    $query,
    'metadata',
    'preferences.theme = "dark" AND notifications.enabled = "true"',
    DatabaseDriver::MYSQL
);

// 10. Requête complexe avec groupes imbriqués
$complexQuery = '(role = "admin" OR role = "manager") AND (status = "active" OR verified = "true")';
$complexSql = $engine->toSql('data', $complexQuery, DatabaseDriver::MYSQL);
echo "\nRequête complexe :\n" . $complexSql . PHP_EOL;

// 11. Gestion des erreurs
try {
    $engine->parse('age > INVALID');
} catch (InvalidArgumentException $e) {
    echo "\nErreur de parsing : " . $e->getMessage() . PHP_EOL;
}

// 12. Intégration dans un service
class UserSearchService
{
    public function __construct(
        private readonly ClusterQuery $engine
    ) {}

    public function search(string $filter): array
    {
        $query = User::query();
        $this->engine->applyToEloquent(
            $query,
            'user_data',
            $filter,
            DatabaseDriver::MYSQL
        );
        return $query->get()->toArray();
    }
}

// Utilisation du service
$searchService = new UserSearchService($engine);
$users = $searchService->search('role = "admin" AND active = "true"');
echo "\nUtilisateurs trouvés : " . count($users) . PHP_EOL;
```

---

## Voir aussi

- `Parser` - Parseur par défaut des expressions
- `ParserInterface` - Interface du parseur
- `NodeInterface` - Interface des nœuds syntaxiques
- `ConditionNode` - Nœud conditionnel atomique
- `GroupNode` - Nœud logique composite
- `ClusterVO` - Objet de données
- `ClusterVOCollection` - Collection de clusters
- `DatabaseDriver` - Énumération des moteurs de bases de données
- `ClusterService` - Service façade