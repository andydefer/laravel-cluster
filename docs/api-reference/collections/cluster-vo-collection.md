# ClusterVOCollection - Référence Technique

## Description

Collection typée spécialisée pour la gestion d'objets `ClusterVO` avec des capacités de filtrage avancées. Elle fournit une API fluide et chaînable pour interroger et filtrer des clusters.

## Hiérarchie

```
AbstractTypedCollection
    └── ClusterVOCollection
```

**Interfaces :** Aucune (hérite de `AbstractTypedCollection`)

## Rôle principal

Cette collection sert de conteneur intelligent pour des objets `ClusterVO`. Elle permet de :

- Filtrer les clusters selon des critères variés (égalité, comparaison numérique, recherche textuelle)
- Combiner des conditions avec des opérateurs logiques (`AND`, `OR`)
- Grouper des conditions complexes
- Travailler avec des données de tableaux aplatis (flattened arrays)
- Chaîner les appels de manière fluide

La collection préserve toujours le jeu de données original en interne, ce qui permet de réaliser des requêtes complexes avec des conditions `OR` sans perdre le contexte initial.

---

## API / Méthodes publiques

### Filtres d'égalité

#### `where(string $key, mixed $value): self`

Filtre les clusters où la clé spécifiée est égale à la valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$value` | `mixed` | Valeur à comparer |

**Retourne :** `self` - Nouvelle collection contenant uniquement les clusters correspondants

**Exemple :**
```php
$activeClusters = $collection->where('status', 'active');
```

---

#### `andWhere(string $key, mixed $value): self`

Alias de `where()` pour faciliter la lecture des conditions chaînées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$value` | `mixed` | Valeur à comparer |

**Retourne :** `self` - Nouvelle collection contenant uniquement les clusters correspondants

**Exemple :**
```php
$result = $collection
    ->andWhere('status', 'active')
    ->andWhere('role', 'admin');
```

---

#### `whereNot(string $key, mixed $value): self`

Filtre les clusters où la clé spécifiée n'est PAS égale à la valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$value` | `mixed` | Valeur à exclure |

**Retourne :** `self` - Nouvelle collection contenant uniquement les clusters non correspondants

**Exemple :**
```php
$nonInactive = $collection->whereNot('status', 'inactive');
```

---

#### `whereTrue(string $key): self`

Filtre les clusters où la clé spécifiée est égale à la chaîne `'true'`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |

**Retourne :** `self` - Nouvelle collection avec les clusters où la valeur est `'true'`

**Exemple :**
```php
$verifiedUsers = $collection->whereTrue('verified');
```

---

#### `whereFalse(string $key): self`

Filtre les clusters où la clé spécifiée est égale à la chaîne `'false'`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |

**Retourne :** `self` - Nouvelle collection avec les clusters où la valeur est `'false'`

**Exemple :**
```php
$unverifiedUsers = $collection->whereFalse('verified');
```

---

### Opérateurs logiques

#### `orWhere(string $key, mixed $value): self`

Ajoute une condition `OR` au filtre existant. Les clusters qui correspondent soit aux critères existants, soit à la nouvelle condition sont inclus.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$value` | `mixed` | Valeur à comparer |

**Retourne :** `self` - Nouvelle collection avec les résultats combinés

**Exemple :**
```php
$activeOrPending = $collection
    ->where('status', 'active')
    ->orWhere('status', 'pending');
```

---

#### `whereGroup(Closure $callback): self`

Applique un groupe de conditions via un callback. Seuls les clusters qui passent TOUTES les conditions du callback sont inclus.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$callback` | `Closure(ClusterVOCollection): ClusterVOCollection` | Fonction retournant une collection filtrée |

**Retourne :** `self` - Nouvelle collection avec les clusters validant toutes les conditions du groupe

**Exemple :**
```php
$admins = $collection->whereGroup(function ($q) {
    return $q->where('status', 'active')
             ->where('role', 'admin');
});
```

---

#### `orWhereGroup(Closure $callback): self`

Applique un groupe de conditions `OR` via un callback. Les clusters qui passent AU MOINS UNE condition du callback sont inclus.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$callback` | `Closure(ClusterVOCollection): ClusterVOCollection` | Fonction retournant une collection filtrée |

**Retourne :** `self` - Nouvelle collection avec les clusters validant au moins une condition du groupe

**Exemple :**
```php
$activeAdminsOrDoctors = $collection->orWhereGroup(function ($q) {
    return $q->where('status', 'active')
             ->where('role', 'admin');
})->orWhereGroup(function ($q) {
    return $q->where('status', 'active')
             ->where('role', 'doctor');
});
```

---

### Filtres d'existence

#### `whereHas(string $key): self`

Filtre les clusters qui possèdent la clé spécifiée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé à vérifier |

**Retourne :** `self` - Nouvelle collection avec les clusters possédant la clé

**Exemple :**
```php
$withMetadata = $collection->whereHas('metadata');
```

---

#### `whereMissing(string $key): self`

Filtre les clusters qui ne possèdent PAS la clé spécifiée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé à vérifier |

**Retourne :** `self` - Nouvelle collection avec les clusters ne possédant pas la clé

**Exemple :**
```php
$withoutDeleted = $collection->whereMissing('deleted_at');
```

---

### Filtres de liste

#### `whereIn(string $key, array $values): self`

Filtre les clusters dont la valeur est dans le tableau donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$values` | `array<mixed>` | Tableau des valeurs acceptées |

**Retourne :** `self` - Nouvelle collection avec les clusters dont la valeur est dans le tableau

**Exemple :**
```php
$adminsAndDoctors = $collection->whereIn('role', ['admin', 'doctor']);
```

---

#### `whereNotIn(string $key, array $values): self`

Filtre les clusters dont la valeur n'est PAS dans le tableau donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$values` | `array<mixed>` | Tableau des valeurs exclues |

**Retourne :** `self` - Nouvelle collection avec les clusters dont la valeur n'est pas dans le tableau

**Exemple :**
```php
$nonActive = $collection->whereNotIn('status', ['active', 'pending']);
```

---

### Comparaisons numériques

#### `whereGreaterThan(string $key, int|float $value): self`

Filtre les clusters dont la valeur numérique est supérieure à la valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$value` | `int\|float` | Valeur minimale (exclusive) |

**Retourne :** `self` - Nouvelle collection avec les clusters où valeur > seuil

**Exemple :**
```php
$adults = $collection->whereGreaterThan('age', 18);
```

---

#### `whereGreaterThanOrEqual(string $key, int|float $value): self`

Filtre les clusters dont la valeur numérique est supérieure ou égale à la valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$value` | `int\|float` | Valeur minimale (inclusive) |

**Retourne :** `self` - Nouvelle collection avec les clusters où valeur >= seuil

**Exemple :**
```php
$adults = $collection->whereGreaterThanOrEqual('age', 18);
```

---

#### `whereLessThan(string $key, int|float $value): self`

Filtre les clusters dont la valeur numérique est inférieure à la valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$value` | `int\|float` | Valeur maximale (exclusive) |

**Retourne :** `self` - Nouvelle collection avec les clusters où valeur < seuil

**Exemple :**
```php
$minors = $collection->whereLessThan('age', 18);
```

---

#### `whereLessThanOrEqual(string $key, int|float $value): self`

Filtre les clusters dont la valeur numérique est inférieure ou égale à la valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$value` | `int\|float` | Valeur maximale (inclusive) |

**Retourne :** `self` - Nouvelle collection avec les clusters où valeur <= seuil

**Exemple :**
```php
$minors = $collection->whereLessThanOrEqual('age', 17);
```

---

#### `whereBetween(string $key, mixed $min, mixed $max): self`

Filtre les clusters dont la valeur numérique est entre les valeurs minimale et maximale données (inclusives).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$min` | `mixed` | Valeur minimale (inclusive) |
| `$max` | `mixed` | Valeur maximale (inclusive) |

**Retourne :** `self` - Nouvelle collection avec les clusters où valeur est dans l'intervalle

**Exemple :**
```php
$youngAdults = $collection->whereBetween('age', 18, 25);
```

---

#### `whereNotBetween(string $key, mixed $min, mixed $max): self`

Filtre les clusters dont la valeur numérique est en dehors de l'intervalle donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$min` | `mixed` | Valeur minimale (inclusive) |
| `$max` | `mixed` | Valeur maximale (inclusive) |

**Retourne :** `self` - Nouvelle collection avec les clusters où valeur est hors intervalle

**Exemple :**
```php
$outsideRange = $collection->whereNotBetween('age', 18, 65);
```

---

### Filtres NULL

#### `whereNull(string $key): self`

Filtre les clusters dont la valeur est `null`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |

**Retourne :** `self` - Nouvelle collection avec les clusters où la valeur est `null`

**Exemple :**
```php
$incomplete = $collection->whereNull('completed_at');
```

---

#### `whereNotNull(string $key): self`

Filtre les clusters dont la valeur n'est PAS `null`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |

**Retourne :** `self` - Nouvelle collection avec les clusters où la valeur n'est pas `null`

**Exemple :**
```php
$completed = $collection->whereNotNull('completed_at');
```

---

### Recherche textuelle

#### `whereContains(string $key, string $search): self`

Filtre les clusters où la chaîne contient le terme recherché (insensible à la casse).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$search` | `string` | Terme à rechercher |

**Retourne :** `self` - Nouvelle collection avec les clusters contenant le terme

**Exemple :**
```php
$johns = $collection->whereContains('name', 'John');
```

---

#### `whereStartsWith(string $key, string $prefix): self`

Filtre les clusters où la chaîne commence par le préfixe donné (insensible à la casse).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$prefix` | `string` | Préfixe à rechercher |

**Retourne :** `self` - Nouvelle collection avec les clusters commençant par le préfixe

**Exemple :**
```php
$jNames = $collection->whereStartsWith('name', 'J');
```

---

#### `whereEndsWith(string $key, string $suffix): self`

Filtre les clusters où la chaîne se termine par le suffixe donné (insensible à la casse).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$suffix` | `string` | Suffixe à rechercher |

**Retourne :** `self` - Nouvelle collection avec les clusters se terminant par le suffixe

**Exemple :**
```php
$dotCom = $collection->whereEndsWith('email', '.com');
```

---

#### `whereNotLike(string $key, string $search): self`

Filtre les clusters où la chaîne ne contient PAS le terme recherché (insensible à la casse).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$search` | `string` | Terme à exclure |

**Retourne :** `self` - Nouvelle collection avec les clusters ne contenant pas le terme

**Exemple :**
```php
$noJohn = $collection->whereNotLike('name', 'John');
```

---

#### `whereNotStarts(string $key, string $prefix): self`

Filtre les clusters où la chaîne ne commence PAS par le préfixe donné (insensible à la casse).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$prefix` | `string` | Préfixe à exclure |

**Retourne :** `self` - Nouvelle collection avec les clusters ne commençant pas par le préfixe

**Exemple :**
```php
$notJ = $collection->whereNotStarts('name', 'J');
```

---

#### `whereNotEnds(string $key, string $suffix): self`

Filtre les clusters où la chaîne ne se termine PAS par le suffixe donné (insensible à la casse).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$suffix` | `string` | Suffixe à exclure |

**Retourne :** `self` - Nouvelle collection avec les clusters ne se terminant pas par le suffixe

**Exemple :**
```php
$notDotCom = $collection->whereNotEnds('email', '.com');
```

---

### Filtres par closure

#### `whereClosure(Closure $callback): self`

Filtre les clusters en utilisant une fonction de rappel personnalisée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$callback` | `Closure(ClusterVO): bool` | Fonction de filtrage |

**Retourne :** `self` - Nouvelle collection avec les clusters validant le callback

**Exemple :**
```php
$complex = $collection->whereClosure(function ($cluster) {
    return $cluster->get('age') > 25 && $cluster->get('role') === 'admin';
});
```

---

#### `orWhereClosure(Closure $callback): self`

Ajoute une condition `OR` utilisant une fonction de rappel personnalisée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$callback` | `Closure(ClusterVO): bool` | Fonction de filtrage |

**Retourne :** `self` - Nouvelle collection avec les clusters validant le callback ou les conditions existantes

**Exemple :**
```php
$result = $collection
    ->where('status', 'active')
    ->orWhereClosure(function ($cluster) {
        return $cluster->get('role') === 'admin';
    });
```

---

### Récupération

#### `firstWhere(string $key, mixed $value): ?ClusterVO`

Retourne le premier cluster correspondant à la condition donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'attribut à vérifier |
| `$value` | `mixed` | Valeur à comparer |

**Retourne :** `ClusterVO|null` - Premier cluster correspondant ou `null` si aucun trouvé

**Exemple :**
```php
$admin = $collection->firstWhere('role', 'admin');
```

---

#### `get(): array`

Retourne tous les clusters de la collection sous forme de tableau.

**Retourne :** `array<ClusterVO>` - Tableau des clusters

**Exemple :**
```php
$allClusters = $collection->get();
```

---

### Aliases

#### `whereLike(string $key, string $search): self`

Alias de `whereContains()`.

---

#### `whereStarts(string $key, string $prefix): self`

Alias de `whereStartsWith()`.

---

#### `whereEnds(string $key, string $suffix): self`

Alias de `whereEndsWith()`.

---

### Filtres sur tableaux aplatis

Ces méthodes fonctionnent avec des données de tableaux qui ont été "aplaties" (flattened). Par exemple, un tableau `['php', 'js']` devient les clés `tags_php = 'true'` et `tags_js = 'true'`.

#### `whereArrayContains(string $key, mixed $value): self`

Filtre les clusters où un tableau (clé aplatie) contient une valeur spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de base du tableau (ex: 'tags') |
| `$value` | `mixed` | Valeur à rechercher |

**Retourne :** `self` - Nouvelle collection avec les clusters correspondants

**Exemple :**
```php
$phpDevs = $collection->whereArrayContains('tags', 'php');
```

---

#### `whereArrayNotContains(string $key, mixed $value): self`

Filtre les clusters où un tableau (clé aplatie) ne contient PAS une valeur spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de base du tableau |
| `$value` | `mixed` | Valeur à exclure |

**Retourne :** `self` - Nouvelle collection avec les clusters non correspondants

**Exemple :**
```php
$noPhp = $collection->whereArrayNotContains('tags', 'php');
```

---

#### `orWhereArrayContains(string $key, mixed $value): self`

Ajoute une condition `OR` pour la présence d'une valeur dans un tableau.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de base du tableau |
| `$value` | `mixed` | Valeur à rechercher |

**Retourne :** `self` - Nouvelle collection avec les résultats combinés

**Exemple :**
```php
$result = $collection
    ->where('status', 'active')
    ->orWhereArrayContains('tags', 'php');
```

---

#### `whereArrayContainsAny(string $key, array $values): self`

Filtre les clusters où un tableau contient AU MOINS UNE des valeurs données.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de base du tableau |
| `$values` | `array<mixed>` | Valeurs à rechercher |

**Retourne :** `self` - Nouvelle collection avec les clusters correspondants

**Exemple :**
```php
$phpOrJs = $collection->whereArrayContainsAny('tags', ['php', 'js']);
```

---

#### `whereArrayContainsAll(string $key, array $values): self`

Filtre les clusters où un tableau contient TOUTES les valeurs données.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de base du tableau |
| `$values` | `array<mixed>` | Valeurs qui doivent toutes être présentes |

**Retourne :** `self` - Nouvelle collection avec les clusters correspondants

**Exemple :**
```php
$fullStack = $collection->whereArrayContainsAll('tags', ['php', 'js']);
```

---

#### `whereArraySize(string $key, int $size): self`

Filtre les clusters où un tableau a une taille spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de base du tableau |
| `$size` | `int` | Taille attendue |

**Retourne :** `self` - Nouvelle collection avec les clusters correspondants

**Exemple :**
```php
$twoTags = $collection->whereArraySize('tags', 2);
```

---

#### `whereArraySizeGreaterThan(string $key, int $size): self`

Filtre les clusters où un tableau a une taille supérieure à la valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de base du tableau |
| `$size` | `int` | Taille minimale (exclusive) |

**Retourne :** `self` - Nouvelle collection avec les clusters correspondants

**Exemple :**
```php
$manyTags = $collection->whereArraySizeGreaterThan('tags', 3);
```

---

#### `whereArraySizeLessThan(string $key, int $size): self`

Filtre les clusters où un tableau a une taille inférieure à la valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de base du tableau |
| `$size` | `int` | Taille maximale (exclusive) |

**Retourne :** `self` - Nouvelle collection avec les clusters correspondants

**Exemple :**
```php
$fewTags = $collection->whereArraySizeLessThan('tags', 3);
```

---

#### `whereArrayEmpty(string $key): self`

Filtre les clusters où un tableau est vide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de base du tableau |

**Retourne :** `self` - Nouvelle collection avec les clusters correspondants

**Exemple :**
```php
$noTags = $collection->whereArrayEmpty('tags');
```

---

#### `whereArrayNotEmpty(string $key): self`

Filtre les clusters où un tableau n'est PAS vide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de base du tableau |

**Retourne :** `self` - Nouvelle collection avec les clusters correspondants

**Exemple :**
```php
$hasTags = $collection->whereArrayNotEmpty('tags');
```

---

## Cas d'utilisation

### Cas 1 : Filtrer les administrateurs actifs

Sélectionner les clusters d'administrateurs actifs âgés de plus de 25 ans.

```php
$collection = new ClusterVOCollection();
// Ajout des clusters...

$admins = $collection
    ->where('status', 'active')
    ->where('role', 'admin')
    ->whereGreaterThan('age', 25)
    ->get();

foreach ($admins as $admin) {
    echo $admin->get('name');
}
```

---

### Cas 2 : Recherche multi-critères avec OR

Trouver les clusters actifs OU ceux en attente avec le rôle guest.

```php
$result = $collection
    ->where('status', 'active')
    ->orWhereGroup(function ($q) {
        return $q->where('status', 'pending')
                 ->where('role', 'guest');
    })
    ->get();
```

---

### Cas 3 : Filtrage sur tableaux de compétences

Trouver les clusters qui maîtrisent à la fois PHP et JavaScript.

```php
$fullStackDevs = $collection
    ->whereArrayContainsAll('skills', ['php', 'js'])
    ->get();
```

---

### Cas 4 : Recherche textuelle et chaînage

Rechercher les utilisateurs dont le nom contient "John" et qui sont vérifiés.

```php
$verifiedJohns = $collection
    ->whereContains('name', 'John')
    ->whereTrue('verified')
    ->get();
```

---

## Gestion des erreurs

La classe `ClusterVOCollection` ne lève pas directement d'exceptions. Elle délègue la validation des types à la classe parente `AbstractTypedCollection` qui peut lever des exceptions si un objet de type incorrect est ajouté.

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un objet non `ClusterVO` | `InvalidArgumentException` | Dépend de l'implémentation parente |

Les méthodes de filtrage retournent toujours une nouvelle instance de `ClusterVOCollection`, même si le résultat est vide. Aucune exception n'est levée pour un filtre sans résultat.

---

## Intégration

`ClusterVOCollection` s'intègre avec :

- **`ClusterVO`** : L'objet manipulé par la collection
- **`AbstractTypedCollection`** : Fournit la structure de base de la collection typée
- **`FlatArrayService`** : Utilisé via `ClusterVO::toArray()` pour accéder aux données aplaties

La collection peut être utilisée avec d'autres composants du package comme les services de clustering ou les repositories.

---

## Performance

### Complexité algorithmique

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `where()`, `andWhere()` | O(n) | Parcourt tous les éléments |
| `orWhere()` | O(n) + O(m) | Parcourt les éléments filtrés + originaux |
| `whereGroup()` | O(n * m) | n = éléments, m = conditions dans le groupe |
| `whereArrayContains*()` | O(n * k) | k = nombre de clés du tableau aplati |
| `get()` | O(1) | Retourne directement le tableau interne |

### Optimisations

- Les filtres créent une **nouvelle collection** à chaque appel, ce qui évite de muter l'originale
- La collection préserve le jeu de données original (`$originalItems`) pour supporter les requêtes `OR`
- Les comparaisons strictes (`===`) sont utilisées pour les opérations d'égalité
- Les identifiants de clusters sont basés sur `spl_object_id()` pour une comparaison fiable des instances

### Considérations mémoire

- Chaque opération de filtrage crée une nouvelle collection avec un nouveau tableau d'items
- Le jeu de données original est conservé dans chaque nouvelle collection pour les futures requêtes `OR`
- Pour de très grandes collections (> 10 000 éléments), considérez l'utilisation de requêtes directes plutôt que le filtrage en mémoire

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (nécessite PHP 8.0+) |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

// Création de la collection
$collection = new ClusterVOCollection();

// Ajout de clusters
$collection->add(new ClusterVO([
    'id' => 1,
    'name' => 'John Doe',
    'status' => 'active',
    'role' => 'admin',
    'verified' => 'true',
    'age' => 30,
    'tags' => ['php', 'js', 'docker'],
]));

$collection->add(new ClusterVO([
    'id' => 2,
    'name' => 'Jane Smith',
    'status' => 'active',
    'role' => 'doctor',
    'verified' => 'true',
    'age' => 25,
    'tags' => ['php', 'python'],
]));

$collection->add(new ClusterVO([
    'id' => 3,
    'name' => 'Bob Johnson',
    'status' => 'inactive',
    'role' => 'admin',
    'verified' => 'false',
    'age' => 22,
    'tags' => ['ruby', 'go'],
]));

// Requête : admins actifs, âgés de plus de 25 ans, avec PHP dans leurs compétences
$qualifiedAdmins = $collection
    ->where('status', 'active')
    ->where('role', 'admin')
    ->whereGreaterThan('age', 25)
    ->whereArrayContains('tags', 'php')
    ->get();

// Résultat : seulement John Doe (id=1)
foreach ($qualifiedAdmins as $admin) {
    echo $admin->get('name') . PHP_EOL;
}

// Requête avec OR : actifs OU admins vérifiés
$activeOrVerifiedAdmins = $collection
    ->where('status', 'active')
    ->orWhereGroup(function ($q) {
        return $q->where('role', 'admin')
                 ->whereTrue('verified');
    })
    ->get();

// Résultat : John Doe (id=1) et Jane Smith (id=2)
echo 'Résultat: ' . count($activeOrVerifiedAdmins) . ' clusters' . PHP_EOL;
```

---

## Voir aussi

- `ClusterVO` - Structure de données représentant un cluster
- `AbstractTypedCollection` - Classe parente fournissant les fonctionnalités de base des collections typées
- `FlatArrayService` - Service de normalisation des tableaux aplatis