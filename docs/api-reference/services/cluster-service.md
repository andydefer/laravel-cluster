# ClusterServiceProvider - Technical Reference

## Description

Le fournisseur de services du package Laravel Cluster enregistre les services principaux, lie les interfaces aux implémentations, et ajoute les macros `whereCluster` à Eloquent Builder et aux Collections Laravel.

## Hiérarchie

```
Illuminate\Support\ServiceProvider
    └── ClusterServiceProvider
```

## Rôle principal

Assure l'intégration du package avec Laravel en :
- Enregistrant les services dans le conteneur IOC
- Ajoutant la macro `whereCluster` à `Builder` (Eloquent)
- Ajoutant la macro `whereCluster` à `Collection` (Laravel Collections)
- Enregistrant les fonctions SQL personnalisées pour SQLite

---

## API

### `register(): void`

Enregistre les services du package dans le conteneur Laravel.

**Services enregistrés :**

| Service | Alias | Description |
|---------|-------|-------------|
| `ClusterQuery` | `cluster.query` | Service de requêtes Cluster |
| `ClusterService` | `cluster.service` | Service principal du package |
| `SqlFunctionRegistry` | `cluster.sql_functions` | Registre des fonctions SQL |

**Exemple :**
```php
// Récupération depuis le conteneur
$clusterQuery = app(ClusterQuery::class);
// ou
$clusterQuery = app('cluster.query');
```

---

### `boot(): void`

Initialise les services du package après l'enregistrement de tous les providers.

**Actions effectuées :**
1. Enregistrement des fonctions SQLite personnalisées
2. Ajout de la macro `whereCluster` à `Builder`
3. Ajout de la macro `whereCluster` à `Collection`

---

## Macros

### `Builder::whereCluster(string $column, string $query)`

Ajoute une condition de filtre sur une colonne JSON.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La colonne contenant les données JSON |
| `$query` | `string` | La requête de filtrage |

**Retourne :** `Builder` - L'instance du builder pour le chaînage

**Exemple :**
```php
// Requête simple
$users = User::whereCluster('clusters', 'status=active')->get();

// Requête avec conditions
$users = User::whereCluster('clusters', 'status=active & role=admin')->get();

// Requête avec sous-conditions
$users = User::whereCluster('clusters', 'addresses[city=Kinshasa]')->get();

// Requête avec fonctions SQL
$users = User::whereCluster('clusters', 'COUNT(addresses) > 2')->get();

// Combinaison avec d'autres conditions Eloquent
$users = User::where('name', 'like', '%Doe%')
    ->whereCluster('clusters', 'status=active')
    ->get();
```

---

### `Collection::whereCluster(string $column, string $query)`

Filtre une collection en utilisant une requête Cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | La clé contenant les données dans chaque élément |
| `$query` | `string` | La requête de filtrage |

**Retourne :** `Collection` - Une nouvelle collection filtrée

**Exemple :**
```php
// Filtrage sur une collection
$filtered = $collection->whereCluster('clusters', 'status=active');

// Chaînage avec d'autres méthodes
$filtered = $collection
    ->whereCluster('clusters', 'status=active')
    ->whereCluster('clusters', 'role=admin')
    ->pluck('name');

// Conservation des clés originales
$filtered = $collection->whereCluster('clusters', 'status=active');
// Les clés originales sont préservées
```

---

## Fonctions SQLite personnalisées

Le provider enregistre automatiquement les fonctions suivantes pour SQLite :

| Fonction | Description | Équivalent MySQL |
|----------|-------------|------------------|
| `JSON_LENGTH` | Longueur d'un tableau JSON | `JSON_LENGTH()` |
| `JSON_AVG` | Moyenne des valeurs d'un tableau | - |
| `JSON_SUM` | Somme des valeurs d'un tableau | - |
| `JSON_MIN` | Minimum des valeurs d'un tableau | - |
| `JSON_MAX` | Maximum des valeurs d'un tableau | - |

**Note :** Ces fonctions ne sont enregistrées que pour SQLite. MySQL et PostgreSQL les supportent nativement.

---

## Cas d'utilisation

### Cas 1 : Filtrage Eloquent simple

```php
<?php

use App\Models\User;

$activeAdmins = User::whereCluster('clusters', 'status=active & role=admin')->get();

foreach ($activeAdmins as $user) {
    echo $user->name . "\n";
}
```

### Cas 2 : Filtrage Eloquent avec sous-conditions

```php
$usersInKinshasa = User::whereCluster('clusters', 'addresses[city=Kinshasa]')->get();

// Avec conditions AND
$usersInKinshasaActive = User::whereCluster('clusters', 'addresses[city=Kinshasa & country=RDC]')->get();

// Avec conditions OR
$usersInKinshasaOrParis = User::whereCluster('clusters', 'addresses[city=Kinshasa | city=Paris]')->get();
```

### Cas 3 : Filtrage Eloquent avec fonctions SQL

```php
// Utilisateurs avec plus de 2 adresses
$usersWithManyAddresses = User::whereCluster('clusters', 'COUNT(addresses) > 2')->get();

// Utilisateurs avec une moyenne de scores >= 85
$topUsers = User::whereCluster('clusters', 'AVG(scores) >= 85')->get();

// Combinaison
$users = User::whereCluster('clusters', 'status=active & COUNT(addresses) > 1')->get();
```

### Cas 4 : Filtrage sur Collection

```php
$users = User::all();

// Filtrage en mémoire
$activeUsers = $users->whereCluster('clusters', 'status=active');

// Chaînage avec d'autres méthodes
$adminNames = $users
    ->whereCluster('clusters', 'status=active')
    ->whereCluster('clusters', 'role=admin')
    ->pluck('name');
```

### Cas 5 : Combinaison avec d'autres conditions Eloquent

```php
$users = User::where('email_verified_at', '!=', null)
    ->whereCluster('clusters', 'status=active')
    ->whereCluster('clusters', 'age>18')
    ->orderBy('name')
    ->get();
```

---

## Gestion des erreurs

| Situation | Comportement | Description |
|-----------|--------------|-------------|
| Requête invalide sur Collection | Retourne une collection vide | Les exceptions sont capturées et silencieusement ignorées |
| Requête invalide sur Builder | Exception levée | La requête SQL générée est invalide |
| Driver non reconnu | Utilise SQLite par défaut | Fallback sûr pour les drivers non supportés |

---

## Performance

- **Connexion :** La détection du driver est effectuée à chaque appel de la macro Eloquent
- **Collections :** Les filtres sont appliqués en mémoire, ce qui peut être coûteux pour de grandes collections
- **Cache :** Aucun cache n'est appliqué aux résultats des macros

---

## Compatibilité

| Version Laravel | Support |
|-----------------|---------|
| Laravel 10.x | ✅ Complet |
| Laravel 9.x | ✅ Complet |
| Laravel 8.x | ✅ Complet |

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

use App\Models\User;
use Illuminate\Support\Collection;

// ==================== CONFIGURATION ====================

// Dans config/app.php
'providers' => [
    // ...
    AndyDefer\LaravelCluster\Providers\ClusterServiceProvider::class,
];

// ==================== ELOQUENT BUILDER ====================

// Filtrage simple
$users = User::whereCluster('clusters', 'status=active')->get();

// Conditions multiples
$users = User::whereCluster('clusters', 'status=active & role=admin')->get();

// Sous-conditions
$users = User::whereCluster('clusters', 'addresses[city=Kinshasa]')->get();

// Fonctions SQL
$users = User::whereCluster('clusters', 'COUNT(addresses) > 2')->get();

// Combinaison avec Eloquent
$users = User::where('created_at', '>', now()->subDays(30))
    ->whereCluster('clusters', 'status=active')
    ->orderBy('name')
    ->get();

// ==================== COLLECTIONS ====================

$usersCollection = User::all();

// Filtrage en mémoire
$activeUsers = $usersCollection->whereCluster('clusters', 'status=active');

// Chaînage
$adminNames = $usersCollection
    ->whereCluster('clusters', 'status=active')
    ->whereCluster('clusters', 'role=admin')
    ->pluck('name')
    ->toArray();

// ==================== CONTENEUR ====================

// Récupération des services
$clusterQuery = app(ClusterQuery::class);
$clusterService = app(ClusterService::class);
$sqlRegistry = app(SqlFunctionRegistry::class);

// Via les alias
$clusterQuery = app('cluster.query');
$clusterService = app('cluster.service');
$sqlRegistry = app('cluster.sql_functions');

// ==================== FONCTIONS SQLITE ====================

// Les fonctions suivantes sont automatiquement disponibles en SQLite :
// - JSON_LENGTH(json, path)
// - JSON_AVG(json, path)
// - JSON_SUM(json, path)
// - JSON_MIN(json, path)
// - JSON_MAX(json, path)

// Exemple d'utilisation directe en SQLite
$users = User::whereRaw('JSON_LENGTH(clusters, \'$.addresses\') > 2')->get();
```

---

## Voir aussi

- `ClusterQuery` - Service de requêtes Cluster
- `ClusterService` - Service principal
- `SqlFunctionRegistry` - Registre des fonctions SQL
- `DatabaseDriver` - Énumération des drivers supportés