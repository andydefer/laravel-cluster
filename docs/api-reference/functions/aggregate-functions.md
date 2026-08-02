# Aggregate Functions - Technical Reference

## Description

Les fonctions d'agrégation fournissent des opérations de calcul et de validation sur les données extraites des clusters. Elles permettent d'effectuer des analyses statistiques (moyenne, somme, min, max), des comptages, des vérifications d'existence, des recherches dans les structures de données et des correspondances par expressions régulières.

## Hiérarchie

```
AggregateFunctionInterface
    └── AbstractAggregateFunction
            ├── AllFunction
            ├── AvgFunction
            ├── CountFunction
            ├── ExistsFunction
            ├── HasFunction
            ├── IsEmptyFunction
            ├── LengthFunction
            ├── MatchesFunction
            ├── MaxFunction
            ├── MinFunction
            └── SumFunction
```

## Rôle principal

Les fonctions d'agrégation sont utilisées dans les expressions de requête pour filtrer les clusters en fonction de propriétés calculées. Elles s'exécutent en mémoire sur les collections via le `AggregateEvaluatorService` et le registre `AggregateFunctionRegistry`.

---

# AbstractAggregateFunction

## Description

Classe abstraite fournissant les fonctionnalités communes à toutes les fonctions d'agrégation : résolution des chemins, extraction de valeurs, décodage JSON et extraction de nombres.

## API

### `resolveArg(array $data, mixed $arg): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$arg` | `mixed` | L'argument à résoudre |

**Retourne :** `mixed` - La valeur résolue

**Exemple :**
```php
$data = ['user' => ['profile' => ['age' => 30]]];
$value = $this->resolveArg($data, 'user.profile.age');
// $value = 30
```

### `resolvePath(array $data, string $path): mixed`

Alias de `resolveArg()` pour une sémantique plus claire.

### `extractValue(array $data, string $path): mixed`

Extrait une valeur en utilisant la notation pointée.

### `extractNumbers(array $array): array`

Extrait toutes les valeurs numériques d'une structure de tableau imbriqué.

### `isJson(string $string): bool`

Vérifie si une chaîne est un JSON valide.

---

# AllFunction

## Description

Vérifie que **tous** les éléments d'une collection satisfont une condition clé-valeur.

## API

### `execute(array $data, array $args): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$args` | `array<int, string>` | Arguments : `[path, key, expectedValue]` |

**Retourne :** `bool` - `true` si tous les éléments satisfont la condition

**Exemple :**
```php
$function = new AllFunction();
$data = [
    'items' => [
        ['status' => 'active'],
        ['status' => 'active'],
    ],
];
$result = $function->execute($data, ['items', 'status', 'active']);
// true
```

---

# AvgFunction

## Description

Calcule la moyenne arithmétique des valeurs numériques extraites d'une structure de données.

## API

### `execute(array $data, array $args): float`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$args` | `array<int, string>` | Arguments : `[path]` |

**Retourne :** `float` - La moyenne des valeurs numériques, ou `0.0` si aucune

**Exemple :**
```php
$avg = new AvgFunction();
$data = ['scores' => [80, 90, 100]];
$result = $avg->execute($data, ['scores']);
// 90.0
```

---

# CountFunction

## Description

Compte les éléments d'un tableau ou les caractères d'une chaîne.

## API

### `execute(array $data, array $args): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$args` | `array<int, string>` | Arguments : `[path]` |

**Retourne :** `int` - Le nombre d'éléments ou de caractères

**Exemple :**
```php
$count = new CountFunction();
$data = ['tags' => ['php', 'js', 'css']];
$result = $count->execute($data, ['tags']);
// 3
```

---

# ExistsFunction

## Description

Vérifie l'existence et la non-vacuité d'une valeur à un chemin donné.

## API

### `execute(array $data, array $args): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$args` | `array<int, string>` | Arguments : `[path]` |

**Retourne :** `bool` - `true` si le chemin existe et contient une valeur non vide

**Exemple :**
```php
$exists = new ExistsFunction();
$data = ['user' => ['name' => 'John']];
$result = $exists->execute($data, ['user.name']);
// true
```

---

# HasFunction

## Description

Recherche une valeur dans un tableau ou une paire clé-valeur dans un tableau d'objets.

## API

### `execute(array $data, array $args): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$args` | `array<int, string>` | Arguments : `[path, key, value?]` |

**Retourne :** `bool` - `true` si la valeur ou la paire clé-valeur est trouvée

**Exemples :**
```php
$has = new HasFunction();

// Recherche d'une valeur dans un tableau
$data = ['tags' => ['php', 'js', 'css']];
$result = $has->execute($data, ['tags', 'php']);
// true

// Recherche d'une paire clé-valeur
$data = ['addresses' => [['city' => 'Kinshasa']]];
$result = $has->execute($data, ['addresses', 'city', 'Kinshasa']);
// true
```

---

# IsEmptyFunction

## Description

Détermine si une valeur extraite est considérée comme vide selon des règles spécifiques.

## API

### `execute(array $data, array $args): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$args` | `array<int, string>` | Arguments : `[path]` |

**Retourne :** `bool` - `true` si la valeur est vide

**Exemple :**
```php
$isEmpty = new IsEmptyFunction();
$data = ['tags' => []];
$result = $isEmpty->execute($data, ['tags']);
// true
```

---

# LengthFunction

## Description

Calcule la longueur d'une chaîne ou le nombre d'éléments d'un tableau.

## API

### `execute(array $data, array $args): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$args` | `array<int, string>` | Arguments : `[path]` |

**Retourne :** `int` - La longueur de la chaîne ou le nombre d'éléments

**Exemple :**
```php
$length = new LengthFunction();
$data = ['name' => 'John Doe'];
$result = $length->execute($data, ['name']);
// 8
```

---

# MatchesFunction

## Description

Recherche une valeur correspondant à une expression régulière dans un tableau ou un tableau d'objets.

Cette fonction supporte deux modes d'utilisation :
- Avec 2 arguments : Recherche une regex dans un tableau de valeurs
- Avec 3 arguments : Recherche une regex sur une clé spécifique dans un tableau d'objets

## API

### `execute(array $data, array $args): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$args` | `array<int, string>` | Arguments : `[path, key, pattern?]` |

**Retourne :** `bool` - `true` si la regex correspond à une valeur

**Exemples :**
```php
$matches = new MatchesFunction();

// Recherche d'une regex dans un tableau de valeurs
$data = ['tags' => ['php', 'javascript', 'css']];
$result = $matches->execute($data, ['tags', '/^ja.*/']);
// true (javascript match)

// Recherche d'une regex sur une clé spécifique
$data = ['addresses' => [['city' => 'Kinshasa'], ['city' => 'Paris']]];
$result = $matches->execute($data, ['addresses', 'city', '/^Kin.*/']);
// true (Kinshasa match)

// Regex insensible à la casse
$result = $matches->execute($data, ['tags', '/^ja.*/i']);
// true

// Aucun match
$result = $matches->execute($data, ['tags', '/^python.*/']);
// false
```

---

# MaxFunction

## Description

Trouve la valeur numérique maximale dans un tableau.

## API

### `execute(array $data, array $args): float|int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$args` | `array<int, string>` | Arguments : `[path]` |

**Retourne :** `float|int` - La valeur maximale trouvée, ou `0` si aucune

**Exemple :**
```php
$max = new MaxFunction();
$data = ['scores' => [80, 90, 100]];
$result = $max->execute($data, ['scores']);
// 100
```

---

# MinFunction

## Description

Trouve la valeur numérique minimale dans un tableau.

## API

### `execute(array $data, array $args): float|int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$args` | `array<int, string>` | Arguments : `[path]` |

**Retourne :** `float|int` - La valeur minimale trouvée, ou `0` si aucune

**Exemple :**
```php
$min = new MinFunction();
$data = ['scores' => [80, 90, 70]];
$result = $min->execute($data, ['scores']);
// 70
```

---

# SumFunction

## Description

Calcule la somme des valeurs numériques dans un tableau.

## API

### `execute(array $data, array $args): float`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données source |
| `$args` | `array<int, string>` | Arguments : `[path]` |

**Retourne :** `float` - La somme des valeurs numériques trouvées, ou `0.0` si aucune

**Exemple :**
```php
$sum = new SumFunction();
$data = ['prices' => [100, 200, 300]];
$result = $sum->execute($data, ['prices']);
// 600.0
```

---

## Cas d'utilisation

### Cas 1 : Filtrer les clusters avec plus de 2 adresses

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection;
$collection->add(new ClusterVO([
    'name' => 'John',
    'addresses' => ['a', 'b', 'c'],
]));
$collection->add(new ClusterVO([
    'name' => 'Jane',
    'addresses' => ['a', 'b'],
]));

$result = $collection->whereAggregate('{COUNT(addresses) > 2}');
// Retourne seulement John
```

### Cas 2 : Filtrer les clusters avec une moyenne de scores supérieure à 85

```php
$result = $collection->whereAggregate('{AVG(scores) >= 85}');
```

### Cas 3 : Vérifier l'existence d'une clé dans chaque cluster

```php
$result = $collection->whereAggregate('{EXISTS(settings.notifications)}');
```

### Cas 4 : Rechercher une valeur dans un tableau

```php
$result = $collection->whereAggregate('{HAS(tags, "php")}');
```

### Cas 5 : Vérifier que tous les éléments satisfont une condition

```php
$result = $collection->whereAggregate('{ALL(addresses, country, "RDC")}');
// Retourne les clusters où toutes les adresses sont en RDC
```

### Cas 6 : Recherche par expression régulière sur un tableau

```php
// Tags commençant par "ja"
$result = $collection->whereAggregate('{MATCHES(tags, "/^ja.*/")}');

// Tags insensibles à la casse
$result = $collection->whereAggregate('{MATCHES(tags, "/^ja.*/i")}');

// Villes commençant par "Kin" dans un tableau d'objets
$result = $collection->whereAggregate('{MATCHES(addresses, city, "/^Kin.*/")}');

// Regex avec caractères spéciaux
$result = $collection->whereAggregate('{MATCHES(codes, "/^[A-Z]{3}-\\d{3}$/")}');
```

### Cas 7 : Combinaison d'expressions complexes

```php
// Combinaison avec AND
$result = $collection->whereAggregate(
    '{COUNT(addresses) > 1} & {AVG(scores) >= 80}'
);

// Combinaison avec OR
$result = $collection->whereAggregate(
    '{HAS(tags, "php")} | {HAS(tags, "python")}'
);

// Combinaison de fonctions booléennes
$result = $collection->whereAggregate(
    '{EXISTS(profile)} & {IS_EMPTY(cart)}'
);

// Combinaison avec regex
$result = $collection->whereAggregate(
    '{MATCHES(tags, "/^ja.*/")} & {COUNT(addresses) > 2}'
);

// Nested complexe
$result = $collection->whereAggregate(
    '({COUNT(addresses) > 1} & {AVG(scores) >= 80}) | {HAS(tags, "php")}'
);
```

### Cas 8 : Utilisation avec `whereAggregateDirect`

```php
// Exécution directe sans parsing
$result = $collection->whereAggregateDirect('COUNT', ['addresses']);
// Retourne les clusters où COUNT(addresses) > 0

$result = $collection->whereAggregateDirect('EXISTS', ['profile']);
// Retourne les clusters où profile existe

$result = $collection->whereAggregateDirect('HAS', ['tags', 'php']);
// Retourne les clusters où tags contient 'php'
```

### Cas 9 : Utilisation avec `matchesAggregate`

```php
// Vérifier si un cluster spécifique correspond
$cluster = $collection->first();
$matches = $collection->matchesAggregate($cluster, '{COUNT(addresses) > 2}');

// Utilisation directe
$matches = $collection->matchesAggregateDirect($cluster, 'COUNT', ['addresses']);
```

### Cas 10 : Utilisation avec `getAggregateValue`

```php
// Obtenir la valeur d'agrégation pour un cluster
$cluster = $collection->first();
$count = $collection->getAggregateValue($cluster, 'COUNT', ['addresses']);
$avg = $collection->getAggregateValue($cluster, 'AVG', ['scores']);
$exists = $collection->getAggregateValue($cluster, 'EXISTS', ['profile']);
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fonction inconnue | `InvalidArgumentException` | `Function "{name}" not registered` |
| Nombre d'arguments invalide | `InvalidArgumentException` | Message personnalisé selon la fonction |
| Chemin inexistant | Retourne `null` ou valeur par défaut | - |
| Regex invalide | Retourne `false` | - |

---

## Intégration

Les fonctions d'agrégation sont utilisées par :

- **`ClusterVOCollection`** : via les méthodes `whereAggregate()`, `whereAggregateDirect()`, `matchesAggregate()`, `getAggregateValue()`
- **`AggregateEvaluatorService`** : pour l'évaluation des expressions
- **`AggregateFunctionRegistry`** : pour l'enregistrement et la résolution des fonctions
- **`ClusterQuery`** : pour le filtrage des collections

### Cycle de vie d'une expression d'agrégation

```
1. Expression textuelle: '{COUNT(addresses) > 2}'
   ↓
2. AggregateExpressionParser parse l'expression
   ↓
3. Détection de la fonction COUNT
   ↓
4. Validation des arguments via validateArgs()
   ↓
5. Exécution via AggregateFunctionRegistry::execute()
   ↓
6. Résultat retourné à l'appelant
```

---

## Performance

- **Complexité :** O(n) où n est le nombre d'éléments dans le tableau cible
- **Extraction des nombres :** Récursive, peut être coûteuse pour des structures profondément imbriquées
- **Cache :** Les expressions sont parsées et mises en cache par `AggregateEvaluatorService`
- **MATCHES :** Les regex sont compilées à chaque exécution, utilisez avec parcimonie sur de grands ensembles de données
- **Recommandation :** Éviter les fonctions d'agrégation sur de très grands tableaux (> 10 000 éléments) en mémoire

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection;

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

// 11. Combinaison complexe
$result = $collection->whereAggregate(
    '{COUNT(addresses) > 1} & {AVG(scores) >= 80} & {HAS(tags, "php")}'
);
// John, Bob

// 12. Utilisation directe avec getAggregateValue
$cluster = $collection->first();
$count = $collection->getAggregateValue($cluster, 'COUNT', ['addresses']);
$avg = $collection->getAggregateValue($cluster, 'AVG', ['scores']);
$hasPhp = $collection->getAggregateValue($cluster, 'HAS', ['tags', 'php']);
$matches = $collection->getAggregateValue($cluster, 'MATCHES', ['tags', '/^ja.*/']);

echo "John: COUNT={$count}, AVG={$avg}, HAS_PHP=" . ($hasPhp ? 'yes' : 'no') . ", MATCHES=" . ($matches ? 'yes' : 'no') . "\n";
// John: COUNT=3, AVG=85, HAS_PHP=yes, MATCHES=yes
```

---

## Voir aussi

- [`AggregateEvaluatorService`](Services/AggregateEvaluatorService.md) - Service d'évaluation des expressions
- [`AggregateFunctionRegistry`](Registry/AggregateFunctionRegistry.md) - Registre des fonctions d'agrégation
- [`AggregateExpressionParser`](Parser/AggregateExpressionParser.md) - Parser des expressions d'agrégation
- [`ClusterVOCollection::whereAggregate()`](Collections/ClusterVOCollection.md#whereaggregate) - Filtrage par expression d'agrégation
- [`ClusterVOCollection::whereAggregateDirect()`](Collections/ClusterVOCollection.md#whereaggregatedirect) - Exécution directe
- [`ClusterVOCollection::matchesAggregate()`](Collections/ClusterVOCollection.md#matchesaggregate) - Vérification de cluster
- [`ClusterVOCollection::getAggregateValue()`](Collections/ClusterVOCollection.md#getaggregatevalue) - Obtention de valeur
- [`MatchesFunction`](Functions/MatchesFunction.md) - Fonction d'agrégation pour les expressions régulières