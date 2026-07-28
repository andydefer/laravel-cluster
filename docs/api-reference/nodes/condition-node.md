# ConditionNode - Référence Technique

## Description

Nœud représentant une condition atomique dans une expression de filtrage. Il encapsule une comparaison entre une clé (champ JSON) et une valeur, en utilisant un opérateur de comparaison défini.

## Hiérarchie

```
Node
    └── ConditionNode
```

**Interfaces :** Aucune (hérite de `Node`)

## Rôle principal

`ConditionNode` est le nœud feuille de l'arbre syntaxique. Il représente une condition simple du type `field = value` ou `field LIKE '%value%'`. Il gère :

- L'évaluation d'une condition sur un objet `ClusterVO` (vérification en mémoire)
- La génération de requêtes SQL pour différents moteurs de bases de données (MySQL, PostgreSQL, SQLite)
- L'application de conditions à des requêtes Eloquent
- La gestion des opérateurs de comparaison (`=`, `!=`, `<`, `>`, `<=`, `>=`, `LIKE`, `EXISTS`, etc.)
- La manipulation de données JSON stockées dans des colonnes de base de données

---

## API / Méthodes publiques

### `__construct(string $key, ComparisonOperator $operator, ?string $value = null)`

Initialise un nœud de condition.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du champ à comparer (chemin JSON) |
| `$operator` | `ComparisonOperator` | Opérateur de comparaison à utiliser |
| `$value` | `?string` | Valeur de comparaison (optionnelle) |

**Exemple :**
```php
$condition = new ConditionNode(
    key: 'user_id',
    operator: ComparisonOperator::EQUAL,
    value: '123'
);
```

---

### `evaluate(ClusterVO $data): bool`

Évalue la condition sur un objet `ClusterVO`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `ClusterVO` | Objet contenant les données à évaluer |

**Retourne :** `bool` - `true` si la condition est remplie, `false` sinon

**Comportement spécifique :**
- Gère les clés manquantes de manière intelligente :
  - `EXISTS` → `false` si la clé n'existe pas
  - `NOT_EXISTS` → `true` si la clé n'existe pas
  - `EQUAL` avec valeur `'false'` ou `'null'` → `true` si la clé n'existe pas (équivaut à `null`)
  - Autres opérateurs → `false` si la clé n'existe pas

**Exemple :**
```php
$data = new ClusterVO(['user_id' => 123, 'status' => 'active']);
$condition = new ConditionNode('user_id', ComparisonOperator::EQUAL, '123');

if ($condition->evaluate($data)) {
    echo "Condition remplie";
}
```

---

### `toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string`

Génère la requête SQL pour la condition.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | Nom de la colonne JSON à interroger |
| `$driver` | `DatabaseDriver` | Moteur de base de données cible (MySQL par défaut) |

**Retourne :** `string` - Fragment SQL représentant la condition

**Exceptions :** `InvalidArgumentException` - Si l'opérateur n'est pas supporté

**Exemple :**
```php
$sql = $condition->toSql('data', DatabaseDriver::MYSQL);
// Résultat : "JSON_EXTRACT(data, '$."user_id"') = '123'"
```

---

### `toEloquent(Builder $query, string $column, DatabaseDriver $driver): void`

Applique la condition à une requête Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Instance du constructeur de requête Eloquent |
| `$column` | `string` | Nom de la colonne JSON |
| `$driver` | `DatabaseDriver` | Moteur de base de données |

**Exemple :**
```php
$query = User::query();
$condition->toEloquent($query, 'metadata', DatabaseDriver::MYSQL);
$users = $query->get();
```

---

### `getChildren(): array`

Retourne les nœuds enfants (aucun pour une condition atomique).

**Retourne :** `array` - Tableau vide (nœud feuille)

**Exemple :**
```php
$children = $condition->getChildren();
// Résultat : []
```

---

### Méthodes privées

Les méthodes suivantes sont internes et ne font pas partie de l'API publique :

| Méthode | Rôle |
|---------|------|
| `getJsonPath()` | Valide et retourne le chemin JSON formaté |
| `getMySqlColumn()` | Génère l'expression de colonne pour MySQL |
| `getPostgreSqlColumn()` | Génère l'expression de colonne pour PostgreSQL |
| `getSqliteColumn()` | Génère l'expression de colonne pour SQLite |
| `getComparisonSql()` | Génère la clause de comparaison SQL |
| `escapeString()` | Échappe les caractères dangereux pour SQL |
| `escapeLikePattern()` | Échappe les caractères spéciaux LIKE |
| `convertToLikePattern()` | Convertit la valeur en pattern LIKE |
| `toMySql()` | Génère le fragment SQL MySQL |
| `toPostgreSql()` | Génère le fragment SQL PostgreSQL |
| `toSqlite()` | Génère le fragment SQL SQLite |
| `applyMySqlEloquent()` | Applique la condition à Eloquent (MySQL) |
| `applyPostgreSqlEloquent()` | Applique la condition à Eloquent (PostgreSQL) |
| `applySqliteEloquent()` | Applique la condition à Eloquent (SQLite) |
| `applyComparisonEloquent()` | Applique les opérateurs de comparaison à Eloquent |

---

## Cas d'utilisation

### Cas 1 : Filtrage mémoire sur des données ClusterVO

Filtrer une collection de clusters en mémoire avant de les exporter.

```php
<?php

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$clusters = [
    new ClusterVO(['user_id' => 123, 'status' => 'active']),
    new ClusterVO(['user_id' => 456, 'status' => 'inactive']),
    new ClusterVO(['user_id' => 789, 'status' => 'active']),
];

$condition = new ConditionNode(
    key: 'status',
    operator: ComparisonOperator::EQUAL,
    value: 'active'
);

$filtered = array_filter($clusters, fn($cluster) => $condition->evaluate($cluster));
// Résultat : clusters avec status = 'active'
```

---

### Cas 2 : Génération de requêtes SQL

Générer des requêtes SQL pour différents moteurs de bases de données.

```php
<?php

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

$condition = new ConditionNode(
    key: 'age',
    operator: ComparisonOperator::GREATER_THAN_OR_EQUAL,
    value: '18'
);

// MySQL
$sqlMySql = $condition->toSql('data', DatabaseDriver::MYSQL);
// "CAST(JSON_EXTRACT(data, '$."age"') AS DECIMAL(10,2)) >= '18'"

// PostgreSQL
$sqlPgSql = $condition->toSql('data', DatabaseDriver::PGSQL);
// "(data->>'age')::numeric >= '18'"

// SQLite
$sqlSqlite = $condition->toSql('data', DatabaseDriver::SQLITE);
// "CAST(json_extract(data, '$.age') AS INTEGER) >= '18'"
```

---

### Cas 3 : Intégration avec Eloquent

Utiliser la condition dans une requête Eloquent.

```php
<?php

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use App\Models\User;

$condition = new ConditionNode(
    key: 'metadata->preferences.theme',
    operator: ComparisonOperator::LIKE,
    value: 'dark'
);

$query = User::query();
$condition->toEloquent(
    $query,
    'settings',
    DatabaseDriver::MYSQL
);

$users = $query->get();
// SELECT * FROM users WHERE JSON_EXTRACT(settings, '$."metadata->preferences.theme"') LIKE '%dark%'
```

---

### Cas 4 : Gestion des opérateurs EXISTS et NOT EXISTS

Vérifier l'existence de champs dans les données JSON.

```php
<?php

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Vérifier qu'un champ existe
$exists = new ConditionNode(
    key: 'email',
    operator: ComparisonOperator::EXISTS
);

// Vérifier qu'un champ n'existe pas
$notExists = new ConditionNode(
    key: 'deleted_at',
    operator: ComparisonOperator::NOT_EXISTS
);

$data = new ClusterVO(['email' => 'john@example.com']);
echo $exists->evaluate($data) ? 'Email existe' : 'Email manquant';
// "Email existe"

$data = new ClusterVO(['user_id' => 123]);
echo $notExists->evaluate($data) ? 'Non supprimé' : 'Supprimé';
// "Non supprimé"
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Clé JSON invalide | `InvalidArgumentException` | `Invalid JSON key: {$key}` |
| Opérateur non supporté (SQL) | `InvalidArgumentException` | `Unsupported operator: {$operator->name}` |
| Opérateur non supporté (Eloquent) | `InvalidArgumentException` | `Unsupported operator: {$operator->name}` |

### Sécurité

La classe intègre plusieurs mesures de sécurité :

1. **Validation des clés JSON** : Les clés sont validées par expression régulière (`/^[a-zA-Z0-9_\-]+$/`) pour prévenir les injections
2. **Échappement SQL** : Les valeurs sont échappées avec `addslashes()`
3. **Paramètres liés Eloquent** : Les requêtes Eloquent utilisent des paramètres liés (`?`) pour prévenir les injections
4. **Échappement LIKE** : Les caractères spéciaux (`%`, `_`, `\`) sont échappés pour les opérateurs LIKE

---

## Intégration

`ConditionNode` s'intègre avec :

- **`Node`** : Classe parente abstraite
- **`ComparisonOperator`** : Énumération des opérateurs de comparaison
- **`DatabaseDriver`** : Énumération des moteurs de bases de données
- **`ClusterVO`** : Objet de données évalué
- **Eloquent `Builder`** : Construction de requêtes SQL

Cette classe est typiquement utilisée comme nœud feuille dans les arbres syntaxiques construits par des analyseurs de requêtes ou des moteurs de filtrage.

---

## Performance

### Complexité algorithmique

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `evaluate()` | O(1) | Accès direct au tableau des données |
| `toSql()` | O(1) | Construction de chaîne simple |
| `toEloquent()` | O(1) | Application directe du whereRaw |

### Optimisations

- Utilisation de `match` pour une sélection rapide des opérateurs
- Pas de boucles ou de récursion (nœud feuille)
- Génération de SQL directe sans parsing supplémentaire
- Utilisation de paramètres liés pour les requêtes Eloquent (sécurité et performance)

### Considérations mémoire

- Objet immuable (propriétés `readonly`)
- Aucune allocation de mémoire importante
- Pas de cache de résultats

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (nécessite PHP 8.0+) |

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

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use App\Models\User;

// 1. Création d'une condition
$condition = new ConditionNode(
    key: 'age',
    operator: ComparisonOperator::GREATER_THAN_OR_EQUAL,
    value: '18'
);

// 2. Évaluation en mémoire
$data = new ClusterVO(['age' => 25, 'name' => 'John']);
$isAdult = $condition->evaluate($data);
echo $isAdult ? 'Adulte' : 'Mineur';
// "Adulte"

// 3. Génération SQL MySQL
$sql = $condition->toSql('data', DatabaseDriver::MYSQL);
echo "MySQL: " . $sql . PHP_EOL;
// "CAST(JSON_EXTRACT(data, '$."age"') AS DECIMAL(10,2)) >= '18'"

// 4. Génération SQL PostgreSQL
$sql = $condition->toSql('data', DatabaseDriver::PGSQL);
echo "PostgreSQL: " . $sql . PHP_EOL;
// "(data->>'age')::numeric >= '18'"

// 5. Génération SQL SQLite
$sql = $condition->toSql('data', DatabaseDriver::SQLITE);
echo "SQLite: " . $sql . PHP_EOL;
// "CAST(json_extract(data, '$.age') AS INTEGER) >= '18'"

// 6. Application à une requête Eloquent
$query = User::query();
$condition->toEloquent($query, 'metadata', DatabaseDriver::MYSQL);
$adults = $query->get();
echo "Nombre d'adultes : " . $adults->count() . PHP_EOL;

// 7. Condition LIKE avec pattern
$likeCondition = new ConditionNode(
    key: 'name',
    operator: ComparisonOperator::LIKE,
    value: 'John'
);

$sql = $likeCondition->toSql('data', DatabaseDriver::MYSQL);
echo $sql . PHP_EOL;
// "JSON_EXTRACT(data, '$."name"') LIKE '%John%'"

// 8. Condition EXISTS
$existsCondition = new ConditionNode(
    key: 'email',
    operator: ComparisonOperator::EXISTS
);

$data = new ClusterVO(['user_id' => 123, 'email' => 'test@example.com']);
echo $existsCondition->evaluate($data) ? 'Email présent' : 'Email absent';
// "Email présent"

// 9. Condition avec clé manquante (EQUAL à 'null')
$nullCondition = new ConditionNode(
    key: 'deleted_at',
    operator: ComparisonOperator::EQUAL,
    value: 'null'
);

$data = new ClusterVO(['user_id' => 123]);
echo $nullCondition->evaluate($data) ? 'Non supprimé' : 'Supprimé';
// "Non supprimé"
```

---

## Voir aussi

- `Node` - Classe parente abstraite des nœuds
- `ComparisonOperator` - Énumération des opérateurs de comparaison
- `DatabaseDriver` - Énumération des moteurs de bases de données
- `ClusterVO` - Objet de données évalué
- `LogicalNode` - Nœud logique (AND, OR, NOT)
- `TokenRecordCollection` - Collection de tokens pour l'analyse syntaxique