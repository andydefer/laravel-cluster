# SqlFunctionRegistry - Technical Reference

## Description

Le registre des fonctions SQL gère l'ensemble des fonctions SQL disponibles pour les requêtes sur données JSON. Il assure l'enregistrement, la résolution et l'exécution des fonctions avec génération de SQL adaptée à chaque driver de base de données.

## Hiérarchie

```
SqlFunctionRegistry
    └── Implémente le pattern Registry
```

## Rôle principal

Centralise l'accès aux fonctions SQL (COUNT, SUM, AVG, MIN, MAX, LENGTH, JSON_LENGTH, REGEXP) et fournit une interface unifiée pour :
- La génération de SQL spécifique à chaque driver
- L'exécution en mémoire des fonctions
- La validation des arguments
- La découverte des types de retour

---

## API

### `__construct()`

Initialise le registre et enregistre les fonctions par défaut.

**Fonctions par défaut :**
- `CountFunction`
- `SumFunction`
- `AvgFunction`
- `MinFunction`
- `MaxFunction`
- `LengthFunction`
- `JsonLengthFunction`
- `RegexpFunction`

---

### `register(SqlFunctionInterface $function): self`

Enregistre une fonction SQL dans le registre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$function` | `SqlFunctionInterface` | La fonction à enregistrer |

**Retourne :** `self` - L'instance du registre pour le chaînage

**Exemple :**
```php
$registry = new SqlFunctionRegistry();
$registry->register(new CustomFunction());
```

---

### `has(string $name): bool`

Vérifie si une fonction est enregistrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction (insensible à la casse) |

**Retourne :** `bool` - `true` si la fonction est enregistrée

**Exemple :**
```php
$registry->has('COUNT'); // true
$registry->has('UNKNOWN'); // false
```

---

### `get(string $name): ?SqlFunctionInterface`

Récupère une fonction enregistrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction (insensible à la casse) |

**Retourne :** `SqlFunctionInterface|null` - L'instance de la fonction ou `null`

**Exemple :**
```php
$function = $registry->get('COUNT');
if ($function) {
    $sql = $function->toSql('clusters', 'addresses', DatabaseDriver::MYSQL);
}
```

---

### `toSql(string $name, string $column, string $path, DatabaseDriver $driver): ?string`

Génère l'expression SQL pour une fonction enregistrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |
| `$column` | `string` | La colonne contenant les données JSON |
| `$path` | `string` | Le chemin JSON dans la colonne |
| `$driver` | `DatabaseDriver` | Le driver de base de données |

**Retourne :** `string|null` - L'expression SQL, ou `null` si la fonction n'est pas enregistrée

**Exemple :**
```php
$sql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses')

$sql = $registry->toSql('AVG', 'clusters', 'scores', DatabaseDriver::MYSQL);
// AVG(CAST(JSON_EXTRACT(clusters, '$.scores') AS DECIMAL(10,2)))

$sql = $registry->toSql('REGEXP', 'clusters', 'name', DatabaseDriver::MYSQL);
// JSON_EXTRACT(clusters, '$.name')
```

---

### `execute(string $name, mixed $value): mixed`

Exécute une fonction enregistrée sur une valeur (évaluation en mémoire).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |
| `$value` | `mixed` | La valeur à traiter |

**Retourne :** `mixed` - Le résultat de la fonction, ou la valeur originale si non enregistrée

**Exemple :**
```php
$result = $registry->execute('COUNT', ['a', 'b', 'c']); // 3
$result = $registry->execute('SUM', [10, 20, 30]); // 60.0
$result = $registry->execute('REGEXP', 'hello'); // 'hello'
```

---

### `getDefaultValue(string $name): mixed`

Retourne la valeur par défaut pour une fonction.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `mixed` - La valeur par défaut, ou `null` si la fonction n'est pas trouvée

**Exemple :**
```php
$default = $registry->getDefaultValue('COUNT'); // 0
$default = $registry->getDefaultValue('AVG'); // 0.0
$default = $registry->getDefaultValue('REGEXP'); // 0
```

---

### `validateArgs(string $name, array $args): bool`

Valide les arguments pour une fonction enregistrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |
| `$args` | `array<int, mixed>` | Les arguments à valider |

**Retourne :** `bool` - `true` si les arguments sont valides

**Exemple :**
```php
$valid = $registry->validateArgs('COUNT', ['addresses']); // true
$valid = $registry->validateArgs('COUNT', ['addresses', 'city']); // false
$valid = $registry->validateArgs('REGEXP', ['name', '^John.*']); // true
```

---

### `all(): array`

Retourne toutes les fonctions enregistrées.

**Retourne :** `array<string, SqlFunctionInterface>` - Tableau des fonctions indexées par nom

---

### `getReturnType(string $name): ?string`

Retourne le type de retour pour une fonction enregistrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `string|null` - Le type de retour (`'int'`, `'float'`, `'string'`, `'bool'`) ou `null`

**Exemple :**
```php
$type = $registry->getReturnType('COUNT'); // 'int'
$type = $registry->getReturnType('AVG'); // 'float'
$type = $registry->getReturnType('REGEXP'); // 'int'
```

---

### `getNames(): array`

Retourne les noms de toutes les fonctions enregistrées.

**Retourne :** `array<string>` - Tableau des noms de fonctions

---

## Cas d'utilisation

### Cas 1 : Génération de SQL pour différents drivers

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$registry = new SqlFunctionRegistry();

// SQLite
$sql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::SQLITE);
// json_array_length(clusters, '$.addresses')

// MySQL
$sql = $registry->toSql('AVG', 'clusters', 'scores', DatabaseDriver::MYSQL);
// AVG(CAST(JSON_EXTRACT(clusters, '$.scores') AS DECIMAL(10,2)))

// PostgreSQL
$sql = $registry->toSql('SUM', 'clusters', 'prices', DatabaseDriver::PGSQL);
// (clusters->>'prices')::numeric

// REGEXP MySQL
$sql = $registry->toSql('REGEXP', 'clusters', 'name', DatabaseDriver::MYSQL);
// JSON_EXTRACT(clusters, '$.name')
```

### Cas 2 : Évaluation en mémoire

```php
// Évaluation directe sur une valeur
$count = $registry->execute('COUNT', ['a', 'b', 'c']); // 3
$sum = $registry->execute('SUM', [10, 20, 30]); // 60.0
$avg = $registry->execute('AVG', [80, 90, 100]); // 90.0
$regexp = $registry->execute('REGEXP', 'hello'); // 'hello'
```

### Cas 3 : Enregistrement d'une fonction personnalisée

```php
use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;

final class CustomFunction extends AbstractSqlFunction
{
    public function getName(): string { return 'CUSTOM'; }
    public function toSql(...): string { /* ... */ }
    public function execute(mixed $value): mixed { /* ... */ }
    public function getReturnType(): string { return 'int'; }
    public function validateArgs(array $args): bool { return count($args) === 1; }
}

$registry = new SqlFunctionRegistry();
$registry->register(new CustomFunction());
```

### Cas 4 : Validation d'arguments

```php
// COUNT attend 1 argument
$valid = $registry->validateArgs('COUNT', ['addresses']);
// true

$valid = $registry->validateArgs('COUNT', ['addresses', 'city']);
// false

// REGEXP attend 2 arguments
$valid = $registry->validateArgs('REGEXP', ['name', '^John.*']);
// true

$valid = $registry->validateArgs('REGEXP', ['name']);
// false
```

### Cas 5 : Utilisation de REGEXP dans une requête

```php
use App\Models\User;

// Utilisateurs dont le nom commence par "John"
$users = User::whereCluster('clusters', 'REGEXP(name, "^John.*")')->get();

// Utilisateurs avec un email Gmail
$users = User::whereCluster('clusters', 'REGEXP(email, ".*@gmail\.com$")')->get();

// Combinaison avec d'autres conditions
$users = User::whereCluster('clusters', 'REGEXP(name, "^John.*") & status=active')->get();
```

### Cas 6 : Filtrage avec valeurs booléennes 'yes'/'no'

```php
use App\Models\User;

// Utilisateurs vérifiés
$users = User::whereCluster('clusters', 'verified=yes')->get();

// Utilisateurs non vérifiés
$users = User::whereCluster('clusters', 'verified=no')->get();

// Utilisateurs actifs et vérifiés
$users = User::whereCluster('clusters', 'status=active & verified=yes')->get();

// Utilisateurs avec une moyenne de scores >= 85
$users = User::whereCluster('clusters', 'AVG(scores) >= 85 & verified=yes')->get();
```

### Cas 7 : Utilisation avec ClusterVOCollection

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John Doe',
    'status' => 'active',
    'verified' => 'yes',
    'scores' => [80, 90, 85],
]));
$collection->add(new ClusterVO([
    'name' => 'Jane Smith',
    'status' => 'active',
    'verified' => 'no',
    'scores' => [70, 75, 80],
]));
$collection->add(new ClusterVO([
    'name' => 'Bob Johnson',
    'status' => 'inactive',
    'verified' => 'yes',
    'scores' => [95, 98, 92],
]));

// Filtrage avec valeurs booléennes
$activeVerified = $collection->whereQuery('status=active & verified=yes');
// John Doe uniquement

// Filtrage avec fonction d'agrégation
$highScores = $collection->whereAggregate('{AVG(scores) >= 85} & verified=yes');
// John Doe, Bob Johnson

// Filtrage avec NOT
$notVerified = $collection->whereQuery('!verified');
// Jane Smith (verified=no)
```

---

## Intégration

Le registre est utilisé par :

- **`ClusterServiceProvider`** : Enregistrement du registre dans le conteneur Laravel
- **`Parser`** : Détection des fonctions SQL dans les expressions
- **`FunctionNode`** : Génération SQL et évaluation des fonctions
- **`ClusterQuery`** : Application des requêtes avec fonctions SQL

---

## Performance

- **Complexité :** O(1) pour l'accès aux fonctions via tableau associatif
- **Mémoire :** Les fonctions sont instanciées une seule fois (singleton via le conteneur Laravel)
- **Cache :** Les résultats de `toSql()` ne sont pas mis en cache (génération à la volée)

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

| Version Database | Support |
|------------------|---------|
| SQLite 3.9+ | ✅ Complet (REGEXP nécessite l'extension) |
| MySQL 5.7+ | ✅ Complet |
| PostgreSQL 9.4+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

// Création du registre
$registry = new SqlFunctionRegistry();

// Vérification des fonctions disponibles
$available = $registry->getNames();
// ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'LENGTH', 'JSON_LENGTH', 'REGEXP']

// Vérification d'une fonction
if ($registry->has('COUNT')) {
    // Génération SQL
    $sql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::SQLITE);
    echo "SQL: $sql\n";
    // SQL: json_array_length(clusters, '$.addresses')

    // Exécution en mémoire
    $result = $registry->execute('COUNT', ['a', 'b', 'c']);
    echo "Result: $result\n";
    // Result: 3

    // Type de retour
    $type = $registry->getReturnType('COUNT');
    echo "Return type: $type\n";
    // Return type: int
}

// Vérification de REGEXP
if ($registry->has('REGEXP')) {
    // Génération SQL
    $sql = $registry->toSql('REGEXP', 'clusters', 'name', DatabaseDriver::MYSQL);
    echo "SQL: $sql\n";
    // SQL: JSON_EXTRACT(clusters, '$.name')

    // Validation des arguments
    $valid = $registry->validateArgs('REGEXP', ['name', '^John.*']);
    var_dump($valid); // bool(true)
}

// Toutes les fonctions
$allFunctions = $registry->all();
foreach ($allFunctions as $name => $function) {
    echo "$name: " . get_class($function) . "\n";
}

// Validation
$valid = $registry->validateArgs('COUNT', ['addresses']);
var_dump($valid); // bool(true)

// Valeur par défaut
$default = $registry->getDefaultValue('COUNT');
var_dump($default); // int(0)
$default = $registry->getDefaultValue('REGEXP');
var_dump($default); // int(0)
```

---

## Voir aussi

- `SqlFunctionInterface` - Interface des fonctions SQL
- `AbstractSqlFunction` - Classe abstraite de base
- `DatabaseDriver` - Énumération des drivers supportés
- `FunctionNode` - Nœud de fonction dans l'AST
- `RegexpFunction` - Fonction SQL pour les expressions régulières
