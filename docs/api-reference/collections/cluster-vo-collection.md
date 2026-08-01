# ClusterVOCollection - Technical Reference

## Description

Collection typée d'objets ClusterVO avec des capacités de filtrage par requête. Elle fournit une interface fluide pour filtrer les clusters en utilisant diverses conditions.

## Hiérarchie

```
AbstractTypedCollection<ClusterVO>
    └── ClusterVOCollection
```

## Rôle principal

Collection spécialisée offrant une API complète de filtrage pour les clusters :

- **Comparaisons simples** : where, whereNot, whereIn, whereNotIn
- **Comparaisons numériques** : whereGreaterThan, whereLessThan, whereBetween
- **Opérations sur chaînes** : whereContains, whereStartsWith, whereEndsWith, whereLikePattern
- **Opérations sur tableaux** : whereArrayContains, whereArrayEmpty, whereArraySize
- **Fonctions d'agrégation** : whereAggregate, whereAggregateDirect
- **Requêtes parsées** : whereQuery

---

## API

### `__construct()`

Crée une nouvelle collection vide.

**Exemple :**
```php
$collection = new ClusterVOCollection();
```

---

### `where(string $key, mixed $value): self`

Filtre les éléments où la clé est égale à la valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à vérifier |
| `$value` | `mixed` | La valeur à comparer |

**Retourne :** `self` - La collection filtrée

**Exemple :**
```php
$result = $collection->where('status', 'active');
```

---

### `whereNot(string $key, mixed $value): self`

Filtre les éléments où la clé n'est pas égale à la valeur donnée.

**Exemple :**
```php
$result = $collection->whereNot('status', 'inactive');
```

---

### `whereYes(string $key): self`

Filtre les éléments où la clé est égale à `'yes'`.

**Exemple :**
```php
$result = $collection->whereYes('verified');
```

---

### `whereNo(string $key): self`

Filtre les éléments où la clé est égale à `'no'`.

**Exemple :**
```php
$result = $collection->whereNo('verified');
```

---

### `orWhere(string $key, mixed $value): self`

Ajoute les éléments correspondants avec une logique OR.

**Exemple :**
```php
$result = $collection
    ->where('status', 'active')
    ->orWhere('status', 'pending');
```

---

### `whereHas(string $key): self`

Filtre les éléments où la clé existe.

**Exemple :**
```php
$result = $collection->whereHas('email');
```

---

### `whereMissing(string $key): self`

Filtre les éléments où la clé n'existe pas.

**Exemple :**
```php
$result = $collection->whereMissing('email');
```

---

### `whereIn(string $key, array $values): self`

Filtre les éléments où la valeur est dans le tableau donné.

**Exemple :**
```php
$result = $collection->whereIn('role', ['admin', 'doctor']);
```

---

### `whereNotIn(string $key, array $values): self`

Filtre les éléments où la valeur n'est pas dans le tableau donné.

**Exemple :**
```php
$result = $collection->whereNotIn('status', ['active', 'pending']);
```

---

### `whereQuery(string $query): self`

Filtre les éléments en utilisant une expression de requête.

**Exemple :**
```php
$result = $collection->whereQuery('status=active & role=admin');
```

---

### `whereGreaterThan(string $key, int|float $value): self`

Filtre les éléments où la valeur est supérieure au seuil.

**Exemple :**
```php
$result = $collection->whereGreaterThan('age', 25);
```

---

### `whereGreaterThanOrEqual(string $key, int|float $value): self`

Filtre les éléments où la valeur est supérieure ou égale au seuil.

**Exemple :**
```php
$result = $collection->whereGreaterThanOrEqual('age', 25);
```

---

### `whereLessThan(string $key, int|float $value): self`

Filtre les éléments où la valeur est inférieure au seuil.

**Exemple :**
```php
$result = $collection->whereLessThan('age', 25);
```

---

### `whereLessThanOrEqual(string $key, int|float $value): self`

Filtre les éléments où la valeur est inférieure ou égale au seuil.

**Exemple :**
```php
$result = $collection->whereLessThanOrEqual('age', 25);
```

---

### `whereBetween(string $key, mixed $min, mixed $max): self`

Filtre les éléments où la valeur est entre min et max inclus.

**Exemple :**
```php
$result = $collection->whereBetween('age', 25, 35);
```

---

### `whereNotBetween(string $key, mixed $min, mixed $max): self`

Filtre les éléments où la valeur n'est pas entre min et max.

**Exemple :**
```php
$result = $collection->whereNotBetween('age', 25, 35);
```

---

### `whereNull(string $key): self`

Filtre les éléments où la valeur est null.

**Exemple :**
```php
$result = $collection->whereNull('email');
```

---

### `whereNotNull(string $key): self`

Filtre les éléments où la valeur n'est pas null.

**Exemple :**
```php
$result = $collection->whereNotNull('email');
```

---

### `whereContains(string $key, string $search): self`

Filtre les éléments où la chaîne contient la recherche.

**Exemple :**
```php
$result = $collection->whereContains('name', 'John');
```

---

### `whereStartsWith(string $key, string $prefix): self`

Filtre les éléments où la chaîne commence par le préfixe.

**Exemple :**
```php
$result = $collection->whereStartsWith('name', 'John');
```

---

### `whereEndsWith(string $key, string $suffix): self`

Filtre les éléments où la chaîne se termine par le suffixe.

**Exemple :**
```php
$result = $collection->whereEndsWith('name', 'Doe');
```

---

### `whereLikePattern(string $key, string $pattern): self`

Filtre les éléments en utilisant un motif LIKE SQL.

**Motifs supportés :**
- `%pattern%` : contient
- `pattern%` : commence par
- `%pattern` : se termine par
- `%pattern1%pattern2%` : multiple conditions

**Exemple :**
```php
$result = $collection->whereLikePattern('name', '%john%');
```

---

### `whereArrayContains(string $key, mixed $value): self`

Filtre les éléments où le tableau contient la valeur.

**Exemple :**
```php
$result = $collection->whereArrayContains('tags', 'php');
```

---

### `whereArrayContainsAny(string $key, array $values): self`

Filtre les éléments où le tableau contient au moins une des valeurs.

**Exemple :**
```php
$result = $collection->whereArrayContainsAny('tags', ['php', 'js']);
```

---

### `whereArrayContainsAll(string $key, array $values): self`

Filtre les éléments où le tableau contient toutes les valeurs.

**Exemple :**
```php
$result = $collection->whereArrayContainsAll('tags', ['php', 'js']);
```

---

### `whereArraySize(string $key, int $size): self`

Filtre les éléments où le tableau a exactement la taille donnée.

**Exemple :**
```php
$result = $collection->whereArraySize('tags', 2);
```

---

### `whereArraySizeGreaterThan(string $key, int $size): self`

Filtre les éléments où le tableau a une taille supérieure.

**Exemple :**
```php
$result = $collection->whereArraySizeGreaterThan('tags', 1);
```

---

### `whereArraySizeLessThan(string $key, int $size): self`

Filtre les éléments où le tableau a une taille inférieure.

**Exemple :**
```php
$result = $collection->whereArraySizeLessThan('tags', 3);
```

---

### `whereArrayEmpty(string $key): self`

Filtre les éléments où le tableau est vide.

**Exemple :**
```php
$result = $collection->whereArrayEmpty('addresses');
```

---

### `whereArrayNotEmpty(string $key): self`

Filtre les éléments où le tableau n'est pas vide.

**Exemple :**
```php
$result = $collection->whereArrayNotEmpty('addresses');
```

---

### `whereAggregate(string $expression): self`

Filtre la collection en utilisant une expression d'agrégation.

**Exemple :**
```php
$result = $collection->whereAggregate('{COUNT(addresses) > 2}');
$result = $collection->whereAggregate('{AVG(scores) >= 85}');
$result = $collection->whereAggregate('{EXISTS(addresses)}');
```

---

### `whereAggregateDirect(string $functionName, array $args = []): self`

Filtre la collection en utilisant un appel de fonction direct.

**Exemple :**
```php
$result = $collection->whereAggregateDirect('COUNT', ['addresses']);
$result = $collection->whereAggregateDirect('EXISTS', ['addresses']);
```

---

### `matchesAggregate(ClusterVO $cluster, string $expression): bool`

Vérifie si un cluster correspond à l'expression d'agrégation.

**Exemple :**
```php
$matches = $collection->matchesAggregate($cluster, '{COUNT(addresses) > 2}');
```

---

### `matchesAggregateDirect(ClusterVO $cluster, string $functionName, array $args = []): bool`

Vérifie si un cluster correspond à un appel de fonction direct.

**Exemple :**
```php
$matches = $collection->matchesAggregateDirect($cluster, 'COUNT', ['addresses']);
```

---

### `getAggregateValue(ClusterVO $cluster, string $functionName, array $args = []): mixed`

Retourne la valeur directe d'une fonction sur un cluster.

**Exemple :**
```php
$count = $collection->getAggregateValue($cluster, 'COUNT', ['addresses']);
$avg = $collection->getAggregateValue($cluster, 'AVG', ['scores']);
```

---

### `validateAggregate(string $expression): bool`

Valide une expression d'agrégation.

**Exemple :**
```php
$valid = $collection->validateAggregate('{COUNT(addresses) > 2}');
```

---

### `getAggregateEvaluator(): AggregateEvaluatorService`

Retourne le service d'évaluation d'agrégation.

---

## Cas d'utilisation

### Cas 1 : Filtrage simple

```php
$active = $collection->where('status', 'active');
$admins = $collection->where('role', 'admin');
$activeAdmins = $collection->where('status', 'active')->where('role', 'admin');
```

### Cas 2 : Filtrage avec OR

```php
$activeOrPending = $collection
    ->where('status', 'active')
    ->orWhere('status', 'pending');
```

### Cas 3 : Filtrage numérique

```php
$adults = $collection->whereGreaterThan('age', 18);
$seniors = $collection->whereGreaterThanOrEqual('age', 65);
```

### Cas 4 : Recherche textuelle

```php
$containsJohn = $collection->whereContains('name', 'John');
$startsJohn = $collection->whereStartsWith('name', 'John');
$likeJohn = $collection->whereLikePattern('name', '%john%');
```

### Cas 5 : Filtrage sur tableaux

```php
$hasPhp = $collection->whereArrayContains('tags', 'php');
$hasPhpOrJs = $collection->whereArrayContainsAny('tags', ['php', 'js']);
$hasPhpAndJs = $collection->whereArrayContainsAll('tags', ['php', 'js']);
$hasTags = $collection->whereArrayNotEmpty('tags');
```

### Cas 6 : Requête parsée

```php
$result = $collection->whereQuery('status=active & role=admin');
$result = $collection->whereQuery('addresses[city=Kinshasa]');
```

### Cas 7 : Agrégation

```php
$moreThan2Addresses = $collection->whereAggregate('{COUNT(addresses) > 2}');
$avgScore85 = $collection->whereAggregate('{AVG(scores) >= 85}');
$existsTags = $collection->whereAggregate('{EXISTS(tags)}');
```

---

## Performance

- **Complexité :** O(n) où n est le nombre de clusters
- **Mémoire :** Chaque filtre crée une nouvelle collection
- **Optimisation :** Les filtres sont exécutés en mémoire avec des générateurs

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

$collection = new ClusterVOCollection();

$collection->add(new ClusterVO([
    'name' => 'John Doe',
    'status' => 'active',
    'role' => 'admin',
    'age' => 30,
    'tags' => ['php', 'js', 'docker'],
    'addresses' => [
        ['city' => 'Kinshasa'],
        ['city' => 'Paris'],
    ],
    'scores' => [80, 90, 85],
]));

$collection->add(new ClusterVO([
    'name' => 'Jane Smith',
    'status' => 'inactive',
    'role' => 'doctor',
    'age' => 25,
    'tags' => ['python', 'react'],
    'addresses' => [
        ['city' => 'Paris'],
    ],
    'scores' => [70, 75, 80],
]));

$collection->add(new ClusterVO([
    'name' => 'Bob Johnson',
    'status' => 'active',
    'role' => 'doctor',
    'age' => 35,
    'tags' => ['php', 'laravel', 'vuejs'],
    'addresses' => [
        ['city' => 'Kinshasa'],
        ['city' => 'London'],
        ['city' => 'Paris'],
    ],
    'scores' => [95, 98, 92],
]));

// ==================== FILTRAGE SIMPLE ====================

$active = $collection->where('status', 'active');
echo "Active: " . $active->count() . "\n"; // 2

// ==================== FILTRAGE AVEC AND ====================

$activeAdmins = $collection
    ->where('status', 'active')
    ->where('role', 'admin');
echo "Active admins: " . $activeAdmins->count() . "\n"; // 1

// ==================== FILTRAGE AVEC OR ====================

$adminOrDoctor = $collection
    ->where('role', 'admin')
    ->orWhere('role', 'doctor');
echo "Admin or doctor: " . $adminOrDoctor->count() . "\n"; // 3

// ==================== FILTRAGE NUMÉRIQUE ====================

$adults = $collection->whereGreaterThan('age', 25);
echo "Adults (>25): " . $adults->count() . "\n"; // 2

$between = $collection->whereBetween('age', 25, 30);
echo "Age between 25 and 30: " . $between->count() . "\n"; // 2

// ==================== RECHERCHE TEXTUELLE ====================

$containsJohn = $collection->whereContains('name', 'John');
echo "Contains John: " . $containsJohn->count() . "\n"; // 1

$likeJohn = $collection->whereLikePattern('name', '%john%');
echo "Like pattern '%john%': " . $likeJohn->count() . "\n"; // 2

// ==================== FILTRAGE SUR TABLEAUX ====================

$hasPhp = $collection->whereArrayContains('tags', 'php');
echo "Has PHP tag: " . $hasPhp->count() . "\n"; // 2

$hasPhpAndJs = $collection->whereArrayContainsAll('tags', ['php', 'js']);
echo "Has PHP and JS: " . $hasPhpAndJs->count() . "\n"; // 1 (John)

$hasTags = $collection->whereArrayNotEmpty('tags');
echo "Has tags: " . $hasTags->count() . "\n"; // 3

// ==================== REQUÊTE PARSÉE ====================

$query = $collection->whereQuery('status=active & role=admin');
echo "Query result: " . $query->count() . "\n"; // 1

$subQuery = $collection->whereQuery('addresses[city=Kinshasa]');
echo "Sub-query result: " . $subQuery->count() . "\n"; // 2

// ==================== AGRÉGATION ====================

$moreThan2 = $collection->whereAggregate('{COUNT(addresses) > 2}');
echo "More than 2 addresses: " . $moreThan2->count() . "\n"; // 1 (Bob)

$avg85 = $collection->whereAggregate('{AVG(scores) >= 85}');
echo "Average score >= 85: " . $avg85->count() . "\n"; // 2 (John, Bob)

// ==================== ÉVALUATION DIRECTE ====================

$cluster = $collection->first();
$count = $collection->getAggregateValue($cluster, 'COUNT', ['addresses']);
echo "First cluster addresses count: $count\n"; // 2

$matches = $collection->matchesAggregate($cluster, '{COUNT(addresses) > 1}');
echo "First cluster matches COUNT > 1: " . ($matches ? 'true' : 'false') . "\n"; // true
```

---

## Voir aussi

- `ClusterVO` - Value Object des clusters
- `ClusterQuery` - Moteur de requêtes
- `AggregateEvaluatorService` - Service d'évaluation d'agrégation
- `AbstractTypedCollection` - Collection typée parente
```