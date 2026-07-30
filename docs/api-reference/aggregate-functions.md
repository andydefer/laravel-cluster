# Aggregate Functions - Technical Reference

## Description

Les fonctions d'agrégation fournissent des opérations de calcul et de validation sur les données extraites des clusters. Elles permettent d'effectuer des analyses statistiques (moyenne, somme, min, max), des comptages, des vérifications d'existence et des recherches dans les structures de données.

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
            ├── MaxFunction
            ├── MinFunction
            └── SumFunction
```

## Rôle principal

Les fonctions d'agrégation sont utilisées dans les expressions de requête pour filtrer les clusters en fonction de propriétés calculées. Elles s'exécutent aussi bien en mémoire (évaluation sur les collections) qu'en SQL (génération de requêtes) grâce au registre `SqlFunctionRegistry`.

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

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fonction inconnue | `InvalidArgumentException` | `Function "{name}" not registered` |
| Nombre d'arguments invalide | `InvalidArgumentException` | Message personnalisé selon la fonction |
| Chemin inexistant | Retourne `null` ou valeur par défaut | - |

---

## Intégration

Les fonctions d'agrégation sont utilisées par :

- **`ClusterVOCollection`** : via les méthodes `whereAggregate()`, `whereAggregateDirect()`, `matchesAggregate()`, `getAggregateValue()`
- **`AggregateEvaluatorService`** : pour l'évaluation des expressions
- **`AggregateFunctionRegistry`** : pour l'enregistrement et la résolution des fonctions
- **`SqlFunctionRegistry`** : pour la génération SQL des fonctions

---

## Performance

- **Complexité :** O(n) où n est le nombre d'éléments dans le tableau cible
- **Extraction des nombres :** Récursive, peut être coûteuse pour des structures profondément imbriquées
- **Cache :** Les expressions sont parsées et mises en cache par `AggregateEvaluatorService`
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
    'tags' => ['php', 'js'],
    'addresses_detail' => [
        ['city' => 'Kinshasa', 'country' => 'RDC'],
        ['city' => 'Paris', 'country' => 'France'],
    ],
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
]));

// Filtrer les clusters avec plus de 2 adresses
$result = $collection->whereAggregate('{COUNT(addresses) > 2}');
// John

// Filtrer les clusters avec une moyenne >= 85
$result = $collection->whereAggregate('{AVG(scores) >= 85}');
// John

// Filtrer les clusters avec un tag "php"
$result = $collection->whereAggregate('{HAS(tags, "php")}');
// John

// Filtrer les clusters dont toutes les adresses sont en RDC
$result = $collection->whereAggregate('{ALL(addresses_detail, country, "RDC")}');
// John (Jane a Paris)

// Combinaison d'expressions
$result = $collection->whereAggregate(
    '{COUNT(addresses) > 1} & {AVG(scores) >= 80}'
);
// John
```

---

## Voir aussi

- `AggregateEvaluatorService` - Service d'évaluation des expressions
- `AggregateFunctionRegistry` - Registre des fonctions d'agrégation
- `SqlFunctionRegistry` - Registre des fonctions SQL
- `ClusterVOCollection::whereAggregate()` - Filtrage par expression d'agrégation