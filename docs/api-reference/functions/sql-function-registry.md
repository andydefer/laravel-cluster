# SqlFunctionRegistry - Référence Technique

## Description

Registre central qui gère l'ensemble des fonctions SQL disponibles pour les requêtes, avec génération de SQL adaptée à chaque driver de base de données (SQLite, MySQL, PostgreSQL).

## Hiérarchie / Implémentations

```
SqlFunctionRegistry (classe finale)
```

## Rôle principal

Le `SqlFunctionRegistry` est le point d'entrée pour toutes les fonctions SQL dans Laravel Cluster. Il :

- **Enregistre** les fonctions SQL (COUNT, SUM, AVG, MIN, MAX, LENGTH, JSON_LENGTH, REGEXP, CONTAINS)
- **Valide** les noms de fonctions selon la convention SCREAMING_SNAKE_CASE
- **Génère** le SQL approprié selon le driver de base de données
- **Exécute** les fonctions en mémoire pour l'évaluation des clusters
- **Valide** les arguments des fonctions
- **Fournit** les métadonnées des fonctions (types de retour, nombre d'arguments min/max)

## API / Méthodes publiques

### `register(SqlFunctionInterface $function): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$function` | `SqlFunctionInterface` | La fonction à enregistrer |

**Retourne :** `self` - L'instance du registre pour le chaînage

**Exceptions :** 
- `InvalidArgumentException` - Si une fonction du même nom est déjà enregistrée
- `InvalidArgumentException` - Si le nom de la fonction est invalide

**Exemple :**
```php
$registry = new SqlFunctionRegistry();
$customFunction = new CustomFunction();
$registry->register($customFunction);
```

---

### `has(string $name): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction (insensible à la casse) |

**Retourne :** `bool` - `true` si la fonction est enregistrée

**Exemple :**
```php
$registry = new SqlFunctionRegistry();
$exists = $registry->has('COUNT'); // true
$exists = $registry->has('UNKNOWN'); // false
```

---

### `get(string $name): ?SqlFunctionInterface`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction (insensible à la casse) |

**Retourne :** `SqlFunctionInterface|null` - L'instance de la fonction, ou `null` si non trouvée

**Exemple :**
```php
$registry = new SqlFunctionRegistry();
$function = $registry->get('COUNT');
// Retourne une instance de CountFunction
```

---

### `toSql(string $name, string $column, string $path, DatabaseDriver $driver, array $args = []): ?string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |
| `$column` | `string` | La colonne JSON contenant les données |
| `$path` | `string` | Le chemin JSON à l'intérieur de la colonne |
| `$driver` | `DatabaseDriver` | Le driver de base de données |
| `$args` | `array` | Arguments supplémentaires pour la fonction |

**Retourne :** `string|null` - L'expression SQL, ou `null` si la fonction n'est pas enregistrée

**Exemple :**
```php
$registry = new SqlFunctionRegistry();

// MySQL
$sql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::MYSQL);
// 'JSON_LENGTH(clusters, '$.addresses')'

// SQLite
$sql = $registry->toSql('CONTAINS', 'clusters', 'languages', DatabaseDriver::SQLITE, ['languages', 'fr']);
// "EXISTS (SELECT 1 FROM json_each(clusters, '$.languages') WHERE value = 'fr')"

// PostgreSQL
$sql = $registry->toSql('SUM', 'clusters', 'prices', DatabaseDriver::PGSQL);
// '(SELECT SUM((value->>'$')::numeric) FROM json_array_elements(clusters->'prices') AS value)'
```

---

### `execute(string $name, mixed $value, array $args = []): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |
| `$value` | `mixed` | La valeur à traiter |
| `$args` | `array` | Arguments supplémentaires pour la fonction |

**Retourne :** `mixed` - Le résultat de la fonction, ou la valeur originale si non enregistrée

**Exemple :**
```php
$registry = new SqlFunctionRegistry();

$result = $registry->execute('COUNT', ['a', 'b', 'c']); // 3
$result = $registry->execute('SUM', [10, 20, 30]); // 60.0
$result = $registry->execute('CONTAINS', ['fr', 'en'], ['languages', 'fr']); // true
```

---

### `validateArgs(string $name, array $args): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |
| `$args` | `array` | Les arguments à valider |

**Retourne :** `bool` - `true` si les arguments sont valides

**Exemple :**
```php
$registry = new SqlFunctionRegistry();

$valid = $registry->validateArgs('COUNT', ['addresses']); // true
$valid = $registry->validateArgs('COUNT', []); // false
$valid = $registry->validateArgs('CONTAINS', ['languages', 'fr']); // true
$valid = $registry->validateArgs('CONTAINS', ['languages']); // false
```

---

### `getMinArgs(string $name): ?int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `int|null` - Le nombre minimum d'arguments, ou `null` si non trouvée

**Exemple :**
```php
$registry = new SqlFunctionRegistry();

$min = $registry->getMinArgs('COUNT'); // 1
$min = $registry->getMinArgs('CONTAINS'); // 2
```

---

### `getMaxArgs(string $name): ?int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `int|null` - Le nombre maximum d'arguments, ou `null` si non trouvée

**Exemple :**
```php
$registry = new SqlFunctionRegistry();

$max = $registry->getMaxArgs('COUNT'); // PHP_INT_MAX
$max = $registry->getMaxArgs('CONTAINS'); // 2
```

---

### `getReturnType(string $name): ?string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `string|null` - Le type de retour (`'int'`, `'float'`, `'string'`, `'bool'`), ou `null` si non trouvée

**Exemple :**
```php
$registry = new SqlFunctionRegistry();

$type = $registry->getReturnType('COUNT'); // 'int'
$type = $registry->getReturnType('SUM'); // 'float'
$type = $registry->getReturnType('CONTAINS'); // 'bool'
```

---

### `getDefaultValue(string $name): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `mixed` - La valeur par défaut, ou `null` si non trouvée

**Exemple :**
```php
$registry = new SqlFunctionRegistry();

$default = $registry->getDefaultValue('COUNT'); // 0
$default = $registry->getDefaultValue('CONTAINS'); // false
```

---

### `all(): array`

**Retourne :** `array<string, SqlFunctionInterface>` - Toutes les fonctions enregistrées

**Exemple :**
```php
$registry = new SqlFunctionRegistry();
$functions = $registry->all();
// ['COUNT' => CountFunction, 'SUM' => SumFunction, ...]
```

---

### `getNames(): array`

**Retourne :** `array<string>` - Les noms de toutes les fonctions enregistrées

**Exemple :**
```php
$registry = new SqlFunctionRegistry();
$names = $registry->getNames();
// ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'LENGTH', 'JSON_LENGTH', 'REGEXP', 'CONTAINS']
```

## Cas d'utilisation

### Cas 1 : Utiliser une fonction dans une requête Cluster

```php
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;

$engine = new ClusterQuery();
$collection = new ClusterVOCollection();
// ... ajout de clusters ...

// La fonction CONTAINS est automatiquement reconnue
$result = $engine->filter($collection, 'CONTAINS(languages, fr)');
```

### Cas 2 : Générer du SQL pour différents drivers

```php
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$registry = new SqlFunctionRegistry();

// Pour MySQL
$mysqlSql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::MYSQL);
// 'JSON_LENGTH(clusters, '$.addresses')'

// Pour SQLite
$sqliteSql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::SQLITE);
// 'json_array_length(clusters, '$.addresses')'

// Pour PostgreSQL
$pgsqlSql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::PGSQL);
// 'jsonb_array_length(clusters->'addresses')'
```

### Cas 3 : Ajouter une fonction personnalisée

```php
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\SqlFunctions\AbstractSqlFunction;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

class CustomFunction extends AbstractSqlFunction
{
    public function getName(): string { return 'CUSTOM'; }
    
    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => "CUSTOM_FN(json_extract($column, '$.$path'))",
            DatabaseDriver::MYSQL => "CUSTOM_FN(JSON_EXTRACT($column, '$.$path'))",
            DatabaseDriver::PGSQL => "CUSTOM_FN($column->>'$path')",
        };
    }
    
    public function execute(mixed $value, array $args = []): mixed
    {
        return is_string($value) ? strlen($value) : 0;
    }
    
    public function getReturnType(): string { return 'int'; }
    public function getMinArgs(): int { return 1; }
    public function getMaxArgs(): int { return PHP_INT_MAX; }
    public function validateArgs(array $args): bool { return count($args) === 1; }
}

$registry = new SqlFunctionRegistry();
$registry->register(new CustomFunction());

// La fonction est maintenant disponible
$sql = $registry->toSql('CUSTOM', 'clusters', 'name', DatabaseDriver::MYSQL);
```

### Cas 4 : Utiliser CONTAINS avec Eloquent

```php
use App\Models\User;

// Utilisateurs qui parlent français
$users = User::whereCluster('clusters', 'CONTAINS(languages, fr)')->get();

// Utilisateurs qui parlent français ET anglais
$users = User::whereCluster('clusters', 'CONTAINS(languages, fr) & CONTAINS(languages, en)')->get();

// Utilisateurs qui parlent français = false (ne parlent pas français)
$users = User::whereCluster('clusters', 'CONTAINS(languages, fr) = false')->get();
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fonction déjà enregistrée | `InvalidArgumentException` | `Function "X" is already registered. Cannot register duplicate.` |
| Nom de fonction invalide | `InvalidArgumentException` | `Invalid function name "X". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.` |
| Fonction non enregistrée | `has()` retourne `false`, `get()` retourne `null` | - |
| `toSql()` sur fonction non enregistrée | - | Retourne `null` |
| `execute()` sur fonction non enregistrée | - | Retourne la valeur d'origine |
| `validateArgs()` sur fonction non enregistrée | - | Retourne `false` |
| Arguments invalides | - | `validateArgs()` retourne `false` |

## Intégration

Le `SqlFunctionRegistry` est utilisé par :

- **`Parser`** : Pour valider les fonctions et leurs arguments pendant l'analyse syntaxique
- **`FunctionNode`** : Pour générer le SQL et exécuter les fonctions en mémoire
- **`ClusterQuery`** : Pour le filtrage des collections
- **`ClusterMacroRegistrar`** : Pour enregistrer les macros `whereCluster`

### Cycle de vie d'une fonction

```
1. Fonction enregistrée dans le registre (register)
   ↓
2. Parser détecte la fonction dans la requête
   ↓
3. Parser valide les arguments via validateArgs()
   ↓
4. FunctionNode créé avec la fonction
   ↓
5. Évaluation : execute() pour les clusters en mémoire
   ↓
6. Génération SQL : toSql() pour les requêtes base de données
```

## Performance

- **Recherche** : O(1) via tableau associatif
- **Enregistrement** : O(1)
- **Génération SQL** : O(1)
- **Mémoire** : Une instance par fonction enregistrée
- **Initialisation** : Les 9 fonctions par défaut sont enregistrées à la construction

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

| Driver | Support |
|--------|---------|
| SQLite | ✅ Complet |
| MySQL | ✅ Complet |
| PostgreSQL | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$registry = new SqlFunctionRegistry();

// 1. Vérifier les fonctions disponibles
var_dump($registry->getNames());
// ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'LENGTH', 'JSON_LENGTH', 'REGEXP', 'CONTAINS']

// 2. Vérifier les métadonnées d'une fonction
var_dump($registry->has('COUNT')); // true
var_dump($registry->getMinArgs('COUNT')); // 1
var_dump($registry->getMaxArgs('COUNT')); // PHP_INT_MAX
var_dump($registry->getReturnType('COUNT')); // 'int'
var_dump($registry->getDefaultValue('COUNT')); // 0

// 3. Valider des arguments
var_dump($registry->validateArgs('COUNT', ['addresses'])); // true
var_dump($registry->validateArgs('COUNT', [])); // false

// 4. Générer du SQL
$sql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::MYSQL);
var_dump($sql); // 'JSON_LENGTH(clusters, '$.addresses')'

// 5. Exécuter en mémoire
$result = $registry->execute('COUNT', ['a', 'b', 'c']);
var_dump($result); // 3

// 6. CONTAINS avec arguments
$result = $registry->execute('CONTAINS', ['fr', 'en', 'es'], ['languages', 'fr']);
var_dump($result); // true

$sql = $registry->toSql('CONTAINS', 'clusters', 'languages', DatabaseDriver::SQLITE, ['languages', 'fr']);
var_dump($sql); // "EXISTS (SELECT 1 FROM json_each(clusters, '$.languages') WHERE value = 'fr')"

// 7. REGEXP avec arguments
$sql = $registry->toSql('REGEXP', 'clusters', 'name', DatabaseDriver::MYSQL, ['name', '^John.*']);
var_dump($sql); // "JSON_EXTRACT(clusters, '$.name') REGEXP '^John.*'"
```

## Voir aussi

- [`SqlFunctionInterface`](Contracts/SqlFunctionInterface.md) - Interface des fonctions SQL
- [`AbstractSqlFunction`](SqlFunctions/AbstractSqlFunction.md) - Classe abstraite pour les fonctions SQL
- [`ContainsFunction`](SqlFunctions/ContainsFunction.md) - Fonction CONTAINS
- [`CountFunction`](SqlFunctions/CountFunction.md) - Fonction COUNT
- [`SumFunction`](SqlFunctions/SumFunction.md) - Fonction SUM
- [`AvgFunction`](SqlFunctions/AvgFunction.md) - Fonction AVG
- [`MinFunction`](SqlFunctions/MinFunction.md) - Fonction MIN
- [`MaxFunction`](SqlFunctions/MaxFunction.md) - Fonction MAX
- [`LengthFunction`](SqlFunctions/LengthFunction.md) - Fonction LENGTH
- [`JsonLengthFunction`](SqlFunctions/JsonLengthFunction.md) - Fonction JSON_LENGTH
- [`RegexpFunction`](SqlFunctions/RegexpFunction.md) - Fonction REGEXP
- [`Parser`](Parser.md) - Analyseur syntaxique utilisant le registre
- [`FunctionNode`](Nodes/FunctionNode.md) - Nœud de fonction dans l'AST
- [`DatabaseDriver`](Enums/DatabaseDriver.md) - Énumération des drivers supportés