# Functions d'agrégation sur les collections - Référence Technique Complète

## Description

Les fonctions d'agrégation permettent d'effectuer des calculs et des validations sur les données des clusters **en mémoire**, sans générer de SQL. Elles sont utilisées avec les méthodes `whereAggregate()`, `matchesAggregate()` et `getAggregateValue()` de `ClusterVOCollection`.

---

## Hiérarchie

```
AggregateFunctionInterface
    └── AbstractAggregateFunction
            ├── AllFunction
            ├── AvgFunction
            ├── CountFunction
            ├── ExistsFunction
            ├── GroupFunction
            ├── HasFunction
            ├── IsEmptyFunction
            ├── LengthFunction
            ├── MatchesFunction
            ├── MaxFunction
            ├── MinFunction
            └── SumFunction
```

---

## Rôle principal

Les fonctions d'agrégation s'exécutent **exclusivement en mémoire** sur les collections PHP. Elles permettent de :

1. **Filtrer** une collection avec `whereAggregate()`
2. **Tester** un cluster individuel avec `matchesAggregate()`
3. **Extraire** des valeurs calculées avec `getAggregateValue()`

---

## Méthodes d'utilisation

### `whereAggregate(string $expression): self`

Filtre la collection en fonction d'une expression d'agrégation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$expression` | `string` | Expression d'agrégation (ex: `{COUNT(addresses) > 2}`) |

**Retourne :** `ClusterVOCollection` - Nouvelle collection filtrée

**Exemple :**
```php
$result = $collection->whereAggregate('{COUNT(addresses) > 2}');
```

---

### `whereAggregateDirect(string $function, array $args, ?string $operator = null, $value = null): self`

Exécute une fonction d'agrégation directement sans parsing.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$function` | `string` | Nom de la fonction (ex: 'COUNT', 'AVG') |
| `$args` | `array` | Arguments de la fonction |
| `$operator` | `?string` | Opérateur de comparaison (optionnel) |
| `$value` | `mixed` | Valeur de comparaison (optionnel) |

**Retourne :** `ClusterVOCollection` - Nouvelle collection filtrée

**Exemple :**
```php
// Filtre les clusters avec COUNT(addresses) > 0
$result = $collection->whereAggregateDirect('COUNT', ['addresses']);

// Filtre les clusters avec COUNT(addresses) > 2
$result = $collection->whereAggregateDirect('COUNT', ['addresses'], '>', 2);
```

---

### `matchesAggregate(ClusterVO $cluster, string $expression): bool`

Vérifie si un cluster correspond à une expression d'agrégation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Le cluster à tester |
| `$expression` | `string` | Expression d'agrégation |

**Retourne :** `bool` - `true` si le cluster correspond

**Exemple :**
```php
$matches = $collection->matchesAggregate($cluster, '{COUNT(addresses) > 2}');
```

---

### `getAggregateValue(ClusterVO $cluster, string $function, array $args): mixed`

Calcule et retourne la valeur d'agrégation pour un cluster spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Le cluster à analyser |
| `$function` | `string` | Nom de la fonction |
| `$args` | `array` | Arguments de la fonction |

**Retourne :** `mixed` - La valeur calculée (int, float, bool, etc.)

**Exemple :**
```php
$count = $collection->getAggregateValue($cluster, 'COUNT', ['addresses']);
$avg = $collection->getAggregateValue($cluster, 'AVG', ['scores']);
```

---

### `validateAggregate(string $expression): bool`

Valide la syntaxe d'une expression d'agrégation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$expression` | `string` | Expression à valider |

**Retourne :** `bool` - `true` si l'expression est valide

**Exemple :**
```php
$valid = $collection->validateAggregate('{COUNT(addresses) > 2}'); // true
$valid = $collection->validateAggregate('{INVALID(addresses) > 2}'); // false
```

---

## Fonctions disponibles

### 1. CountFunction - Compter les éléments

**Description :** Compte les éléments d'un tableau ou les caractères d'une chaîne.

**Signature :** `COUNT(path)`

**Exemple :**
```php
// Compter les adresses
$result = $collection->whereAggregate('{COUNT(addresses) > 2}');

// Compter les caractères (si path est une chaîne)
$result = $collection->whereAggregate('{COUNT(name) > 5}');
```

**Cas d'utilisation :**
```php
// Utilisateurs avec plus de 2 adresses
$usersWithManyAddresses = $collection->whereAggregate('{COUNT(addresses) > 2}');

// Produits avec au moins 3 photos
$products = $collection->whereAggregate('{COUNT(photos) >= 3}');
```

---

### 2. AvgFunction - Calculer la moyenne

**Description :** Calcule la moyenne arithmétique des valeurs numériques.

**Signature :** `AVG(path)`

**Exemple :**
```php
// Moyenne des scores >= 85
$result = $collection->whereAggregate('{AVG(scores) >= 85}');

// Moyenne des prix < 100
$result = $collection->whereAggregate('{AVG(prices) < 100}');
```

**Cas d'utilisation :**
```php
// Étudiants avec moyenne générale >= 80
$topStudents = $collection->whereAggregate('{AVG(grades) >= 80}');

// Produits avec note moyenne > 4.5
$bestProducts = $collection->whereAggregate('{AVG(ratings) > 4.5}');
```

---

### 3. SumFunction - Calculer la somme

**Description :** Calcule la somme des valeurs numériques.

**Signature :** `SUM(path)`

**Exemple :**
```php
// Somme des prix > 500
$result = $collection->whereAggregate('{SUM(prices) > 500}');

// Somme des commandes >= 1000
$result = $collection->whereAggregate('{SUM(orders) >= 1000}');
```

**Cas d'utilisation :**
```php
// Clients avec achats totaux > 1000€
$bigSpenders = $collection->whereAggregate('{SUM(purchases) > 1000}');

// Paniers avec total > 50€
$carts = $collection->whereAggregate('{SUM(items.price) > 50}');
```

---

### 4. MinFunction - Trouver le minimum

**Description :** Trouve la valeur numérique minimale.

**Signature :** `MIN(path)`

**Exemple :**
```php
// Score minimum > 60
$result = $collection->whereAggregate('{MIN(scores) > 60}');

// Prix minimum >= 10
$result = $collection->whereAggregate('{MIN(prices) >= 10}');
```

**Cas d'utilisation :**
```php
// Élèves avec toutes les notes > 50
$consistentStudents = $collection->whereAggregate('{MIN(grades) > 50}');

// Produits avec prix minimum > 5€
$products = $collection->whereAggregate('{MIN(prices) > 5}');
```

---

### 5. MaxFunction - Trouver le maximum

**Description :** Trouve la valeur numérique maximale.

**Signature :** `MAX(path)`

**Exemple :**
```php
// Score maximum < 95
$result = $collection->whereAggregate('{MAX(scores) < 95}');

// Prix maximum <= 1000
$result = $collection->whereAggregate('{MAX(prices) <= 1000}');
```

**Cas d'utilisation :**
```php
// Produits avec prix maximum < 1000€
$affordableProducts = $collection->whereAggregate('{MAX(prices) < 1000}');

// Étudiants avec aucune note > 90
$nonEliteStudents = $collection->whereAggregate('{MAX(grades) <= 90}');
```

---

### 6. LengthFunction - Longueur d'une chaîne

**Description :** Calcule la longueur d'une chaîne ou le nombre d'éléments.

**Signature :** `LENGTH(path)`

**Exemple :**
```php
// Nom de plus de 5 caractères
$result = $collection->whereAggregate('{LENGTH(name) > 5}');

// Description de moins de 100 caractères
$result = $collection->whereAggregate('{LENGTH(description) < 100}');
```

**Cas d'utilisation :**
```php
// Utilisateurs avec nom court (< 5 caractères)
$shortNames = $collection->whereAggregate('{LENGTH(name) < 5}');

// Produits avec code produit de 12 caractères
$products = $collection->whereAggregate('{LENGTH(product_code) = 12}');
```

---

### 7. ExistsFunction - Vérifier l'existence

**Description :** Vérifie qu'un chemin existe et n'est pas vide.

**Signature :** `EXISTS(path)`

**Exemple :**
```php
// Profile existe
$result = $collection->whereAggregate('{EXISTS(profile)}');

// Email existe
$result = $collection->whereAggregate('{EXISTS(email)}');
```

**Cas d'utilisation :**
```php
// Utilisateurs avec profil complet
$completeProfiles = $collection->whereAggregate('{EXISTS(profile)}');

// Commandes avec adresse de livraison
$ordersWithAddress = $collection->whereAggregate('{EXISTS(shipping_address)}');
```

---

### 8. HasFunction - Rechercher une valeur

**Description :** Recherche une valeur dans un tableau ou une paire clé-valeur.

**Signatures :**
- `HAS(path, value)` - Recherche dans un tableau simple
- `HAS(path, key, value)` - Recherche dans un tableau d'objets

**Exemple :**
```php
// Tags contient 'php'
$result = $collection->whereAggregate('{HAS(tags, "php")}');

// Adresse avec ville 'Paris'
$result = $collection->whereAggregate('{HAS(addresses, city, "Paris")}');
```

**Cas d'utilisation :**
```php
// Développeurs PHP
$phpDevs = $collection->whereAggregate('{HAS(skills, "php")}');

// Commandes avec produit 'Laptop'
$laptopOrders = $collection->whereAggregate('{HAS(items, name, "Laptop")}');

// Utilisateurs avec tag 'premium'
$premiumUsers = $collection->whereAggregate('{HAS(tags, "premium")}');
```

---

### 9. AllFunction - Tous les éléments

**Description :** Vérifie que **tous** les éléments satisfont une condition.

**Signature :** `ALL(path, key, expectedValue)`

**Exemple :**
```php
// Toutes les adresses sont en RDC
$result = $collection->whereAggregate('{ALL(addresses, country, "RDC")}');

// Tous les produits sont disponibles
$result = $collection->whereAggregate('{ALL(items, status, "available")}');
```

**Cas d'utilisation :**
```php
// Commandes avec tous les produits en stock
$fullyInStock = $collection->whereAggregate('{ALL(items, in_stock, "yes")}');

// Utilisateurs avec toutes les vérifications passées
$fullyVerified = $collection->whereAggregate('{ALL(verifications, status, "passed")}');
```

---

### 10. MatchesFunction - Expression régulière

**Description :** Recherche une valeur correspondant à une regex.

**Signatures :**
- `MATCHES(path, regex)` - Regex sur un tableau simple
- `MATCHES(path, key, regex)` - Regex sur une clé spécifique

**Exemple :**
```php
// Tags commençant par 'ja'
$result = $collection->whereAggregate('{MATCHES(tags, "/^ja.*/")}');

// Villes commençant par 'Kin'
$result = $collection->whereAggregate('{MATCHES(addresses, city, "/^Kin.*/")}');

// Regex insensible à la casse
$result = $collection->whereAggregate('{MATCHES(tags, "/^ja.*/i")}');
```

**Cas d'utilisation :**
```php
// Emails Gmail
$gmailUsers = $collection->whereAggregate('{MATCHES(emails, "/.*@gmail\\.com$/")}');

// Noms commençant par une majuscule
$properNames = $collection->whereAggregate('{MATCHES(names, "/^[A-Z]/")}');

// Codes postaux français
$frenchPostal = $collection->whereAggregate('{MATCHES(postal_codes, "/^[0-9]{5}$/")}');
```

---

### 11. IsEmptyFunction - Vérifier le vide

**Description :** Détermine si une valeur est considérée comme vide.

**Signature :** `IS_EMPTY(path)`

**Exemple :**
```php
// Panier vide
$result = $collection->whereAggregate('{IS_EMPTY(cart)}');

// Tags vides
$result = $collection->whereAggregate('{IS_EMPTY(tags)}');
```

**Cas d'utilisation :**
```php
// Utilisateurs sans panier
$emptyCarts = $collection->whereAggregate('{IS_EMPTY(cart)}');

// Produits sans description
$noDescription = $collection->whereAggregate('{IS_EMPTY(description)}');
```

---

### 12. GroupFunction - Grouper des expressions

**Description :** Permet de grouper des expressions pour la logique booléenne.

**Signature :** `GROUP({expression1} & {expression2})`

**Exemple :**
```php
// Groupe avec AND
$result = $collection->whereAggregate(
    '{GROUP({COUNT(addresses) > 1} & {AVG(scores) >= 85})}'
);

// Groupe avec OR
$result = $collection->whereAggregate(
    '{GROUP({HAS(tags, "php")} | {HAS(tags, "javascript")})}'
);
```

**Cas d'utilisation :**
```php
// Complexe : Plus de 2 adresses ET moyenne >= 85
$result = $collection->whereAggregate(
    '{GROUP({COUNT(addresses) > 2} & {AVG(scores) >= 85})}'
);

// OU : PHP OU JavaScript
$result = $collection->whereAggregate(
    '{GROUP({HAS(tags, "php")} | {HAS(tags, "javascript")})}'
);
```

---

## Exemples complets

### Exemple 1 : Analyse de données étudiants

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$students = new ClusterVOCollection();

$students->add(new ClusterVO([
    'name' => 'Alice',
    'grades' => [85, 90, 88, 92],
    'subjects' => ['Math', 'Physics', 'Chemistry'],
    'attendance' => [95, 98, 97, 96],
]));
$students->add(new ClusterVO([
    'name' => 'Bob',
    'grades' => [70, 75, 65, 80],
    'subjects' => ['Math', 'Biology'],
    'attendance' => [80, 85, 90, 88],
]));
$students->add(new ClusterVO([
    'name' => 'Charlie',
    'grades' => [95, 92, 98, 94],
    'subjects' => ['Math', 'Physics', 'Chemistry', 'Biology'],
    'attendance' => [90, 92, 95, 93],
]));

// 1. Étudiants avec moyenne >= 85
$topStudents = $students->whereAggregate('{AVG(grades) >= 85}');
// Alice, Charlie

// 2. Étudiants avec au moins 3 matières
$busyStudents = $students->whereAggregate('{COUNT(subjects) >= 3}');
// Alice, Charlie

// 3. Étudiants avec moyenne >= 85 ET au moins 3 matières
$excellentStudents = $students->whereAggregate(
    '{GROUP({AVG(grades) >= 85} & {COUNT(subjects) >= 3})}'
);
// Alice, Charlie

// 4. Étudiants avec présence moyenne > 90
$presentStudents = $students->whereAggregate('{AVG(attendance) > 90}');
// Alice, Charlie

// 5. Étudiants avec note minimale > 80 (consistants)
$consistentStudents = $students->whereAggregate('{MIN(grades) > 80}');
// Alice, Charlie

// 6. Étudiants avec une note parfaite (max = 100)
$perfectStudents = $students->whereAggregate('{MAX(grades) = 100}');
// Aucun

// 7. Utilisation de getAggregateValue
foreach ($students as $student) {
    $avg = $students->getAggregateValue($student, 'AVG', ['grades']);
    $min = $students->getAggregateValue($student, 'MIN', ['grades']);
    $max = $students->getAggregateValue($student, 'MAX', ['grades']);
    $count = $students->getAggregateValue($student, 'COUNT', ['subjects']);
    
    echo $student->get('name') . ": Avg={$avg}, Min={$min}, Max={$max}, Subjects={$count}\n";
}
// Alice: Avg=88.75, Min=85, Max=92, Subjects=3
// Bob: Avg=72.5, Min=65, Max=80, Subjects=2
// Charlie: Avg=94.75, Min=92, Max=98, Subjects=4
```

---

### Exemple 2 : Analyse de ventes

```php
<?php

declare(strict_types=1);

$products = new ClusterVOCollection();

$products->add(new ClusterVO([
    'name' => 'Laptop Pro',
    'sales' => [100, 150, 120, 90, 110, 130],
    'prices' => [1200, 1100, 1300, 1250],
    'ratings' => [4.5, 5.0, 4.0, 4.8],
]));
$products->add(new ClusterVO([
    'name' => 'Phone X',
    'sales' => [200, 180, 220, 190, 210, 195],
    'prices' => [800, 750, 850, 780],
    'ratings' => [4.2, 4.5, 4.0, 4.3],
]));
$products->add(new ClusterVO([
    'name' => 'Tablet Z',
    'sales' => [50, 60, 55, 45, 48, 52],
    'prices' => [500, 480, 520, 490],
    'ratings' => [3.8, 4.0, 3.5, 4.2],
]));

// 1. Produits avec ventes moyennes > 150
$topSellers = $products->whereAggregate('{AVG(sales) > 150}');
// Phone X (200.8)

// 2. Produits avec ventes stables (min > 100)
$stableProducts = $products->whereAggregate('{MIN(sales) > 100}');
// Laptop Pro (90) -> false, Phone X (180) -> true, Tablet Z (45) -> false

// 3. Produits avec prix moyen < 1000
$affordableProducts = $products->whereAggregate('{AVG(prices) < 1000}');
// Phone X, Tablet Z

// 4. Produits avec note moyenne > 4.0
$highRated = $products->whereAggregate('{AVG(ratings) > 4.0}');
// Laptop Pro (4.575), Phone X (4.25)

// 5. Combinaison complexe
$bestProducts = $products->whereAggregate(
    '{GROUP({AVG(sales) > 100} & {AVG(ratings) > 4.0} & {AVG(prices) < 1500})}'
);
// Laptop Pro, Phone X

// 6. Utilisation de whereAggregateDirect
$filtered = $products->whereAggregateDirect('AVG', ['sales'], '>', 100)
    ->whereAggregateDirect('AVG', ['ratings'], '>', 4.0);

// 7. Validation avant utilisation
$expression = '{AVG(sales) > 100}';
if ($products->validateAggregate($expression)) {
    $result = $products->whereAggregate($expression);
}

// 8. Calculs individuels avec getAggregateValue
foreach ($products as $product) {
    $avgSales = $products->getAggregateValue($product, 'AVG', ['sales']);
    $avgRating = $products->getAggregateValue($product, 'AVG', ['ratings']);
    $totalRevenue = $products->getAggregateValue($product, 'SUM', ['prices']);
    $productCount = $products->getAggregateValue($product, 'COUNT', ['prices']);
    
    echo $product->get('name') . ":\n";
    echo "  Ventes moyennes: {$avgSales}\n";
    echo "  Note moyenne: {$avgRating}\n";
    echo "  Revenu total: {$totalRevenue}\n";
    echo "  Nombre de prix: {$productCount}\n";
}
```

---

### Exemple 3 : Filtrage de données complexes

```php
<?php

declare(strict_types=1);

$users = new ClusterVOCollection();

$users->add(new ClusterVO([
    'id' => 1,
    'name' => 'John Doe',
    'profile' => [
        'age' => 30,
        'city' => 'Paris',
        'verified' => true,
        'interests' => ['php', 'laravel', 'docker'],
    ],
    'orders' => [
        ['amount' => 100, 'status' => 'completed'],
        ['amount' => 200, 'status' => 'completed'],
        ['amount' => 150, 'status' => 'pending'],
    ],
    'ratings' => [4.5, 5.0, 4.0, 4.8],
]));

$users->add(new ClusterVO([
    'id' => 2,
    'name' => 'Jane Smith',
    'profile' => [
        'age' => 25,
        'city' => 'London',
        'verified' => false,
        'interests' => ['python', 'django', 'react'],
    ],
    'orders' => [
        ['amount' => 50, 'status' => 'completed'],
        ['amount' => 75, 'status' => 'pending'],
    ],
    'ratings' => [4.0, 4.2, 4.5],
]));

$users->add(new ClusterVO([
    'id' => 3,
    'name' => 'Bob Johnson',
    'profile' => [
        'age' => 35,
        'city' => 'Paris',
        'verified' => true,
        'interests' => ['javascript', 'vuejs', 'node'],
    ],
    'orders' => [
        ['amount' => 300, 'status' => 'completed'],
        ['amount' => 250, 'status' => 'completed'],
        ['amount' => 200, 'status' => 'completed'],
    ],
    'ratings' => [5.0, 4.8, 5.0, 4.9],
]));

// 1. Filtres complexes avec GROUP
$results = $users->whereAggregate(
    '{GROUP({EXISTS(profile.verified)} & {EXISTS(profile.city)} & {COUNT(orders) > 1})}'
);
// John, Bob (vérifiés, avec ville, plus de 1 commande)

// 2. HAS sur tableau d'objets
$phpUsers = $users->whereAggregate('{HAS(profile.interests, "php")}');
// John

// 3. ALL avec conditions
$allCompleted = $users->whereAggregate('{ALL(orders, status, "completed")}');
// Bob (toutes ses commandes sont completed)

// 4. MATCHES sur tableau simple
$laravelUsers = $users->whereAggregate('{MATCHES(profile.interests, "/laravel/")}');
// John

// 5. Combinaison HAS + MATCHES
$techUsers = $users->whereAggregate(
    '{GROUP({HAS(profile.interests, "php")} | {MATCHES(profile.interests, "/java.*/")})}'
);
// John (php), Bob (javascript)

// 6. Métriques complètes avec getAggregateValue
$metrics = [];
foreach ($users as $user) {
    $metrics[$user->get('id')] = [
        'avg_order' => $users->getAggregateValue($user, 'AVG', ['orders.*.amount']),
        'total_spent' => $users->getAggregateValue($user, 'SUM', ['orders.*.amount']),
        'order_count' => $users->getAggregateValue($user, 'COUNT', ['orders']),
        'avg_rating' => $users->getAggregateValue($user, 'AVG', ['ratings']),
        'has_verified' => $users->getAggregateValue($user, 'EXISTS', ['profile.verified']),
    ];
}

print_r($metrics);
// [
//   1 => ['avg_order' => 150, 'total_spent' => 450, 'order_count' => 3, 'avg_rating' => 4.575, 'has_verified' => true],
//   2 => ['avg_order' => 62.5, 'total_spent' => 125, 'order_count' => 2, 'avg_rating' => 4.233, 'has_verified' => false],
//   3 => ['avg_order' => 250, 'total_spent' => 750, 'order_count' => 3, 'avg_rating' => 4.925, 'has_verified' => true],
// ]
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fonction inconnue | `InvalidArgumentException` | `Function "{name}" not registered` |
| Nombre d'arguments invalide | `InvalidArgumentException` | Message personnalisé selon la fonction |
| Chemin inexistant | Retourne `null` ou valeur par défaut | - |
| Regex invalide | Retourne `false` | - |
| Expression mal formée | `RuntimeException` | `Invalid aggregate expression` |

---

## Performance

- **Complexité :** O(n) où n est le nombre d'éléments dans le tableau cible
- **Extraction des nombres :** Récursive, peut être coûteuse pour des structures profondément imbriquées
- **Cache :** Les expressions sont parsées et mises en cache
- **MATCHES :** Les regex sont compilées à chaque exécution
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
$result = $collection->whereAggregate('{COUNT(addresses) > 2}'); // John, Bob

// 2. Filtrer avec AVG
$result = $collection->whereAggregate('{AVG(scores) >= 85}'); // John, Bob

// 3. Filtrer avec SUM
$result = $collection->whereAggregate('{SUM(prices) > 500}'); // John, Bob

// 4. Filtrer avec MIN
$result = $collection->whereAggregate('{MIN(scores) > 75}'); // John, Bob

// 5. Filtrer avec MAX
$result = $collection->whereAggregate('{MAX(scores) < 95}'); // John, Jane

// 6. Filtrer avec EXISTS
$result = $collection->whereAggregate('{EXISTS(profile)}'); // John, Jane, Bob

// 7. Filtrer avec IS_EMPTY
$result = $collection->whereAggregate('{IS_EMPTY(cart)}'); // Jane

// 8. Filtrer avec HAS
$result = $collection->whereAggregate('{HAS(tags, "php")}'); // John, Bob

// 9. Filtrer avec ALL
$result = $collection->whereAggregate('{ALL(addresses_detail, country, "RDC")}'); // John

// 10. Filtrer avec MATCHES
$result = $collection->whereAggregate('{MATCHES(tags, "/^ja.*/")}'); // John (javascript)

// 11. Combinaison complexe avec GROUP
$result = $collection->whereAggregate(
    '{GROUP({COUNT(addresses) > 1} & {AVG(scores) >= 80} & {HAS(tags, "php")})}'
); // John, Bob

// 12. Validation
$valid = $collection->validateAggregate('{COUNT(addresses) > 2}'); // true
$invalid = $collection->validateAggregate('{INVALID(addresses) > 2}'); // false

// 13. Utilisation directe
$result = $collection->whereAggregateDirect('COUNT', ['addresses'], '>', 2);

// 14. Tests individuels
$cluster = $collection->first();
$matches = $collection->matchesAggregate($cluster, '{COUNT(addresses) > 2}');

// 15. Obtention de valeurs
$count = $collection->getAggregateValue($cluster, 'COUNT', ['addresses']);
$avg = $collection->getAggregateValue($cluster, 'AVG', ['scores']);
$hasPhp = $collection->getAggregateValue($cluster, 'HAS', ['tags', 'php']);
$matches = $collection->getAggregateValue($cluster, 'MATCHES', ['tags', '/^ja.*/']);

echo "John: COUNT={$count}, AVG={$avg}, HAS_PHP=" . ($hasPhp ? 'yes' : 'no') . ", MATCHES=" . ($matches ? 'yes' : 'no') . "\n";
// John: COUNT=3, AVG=85, HAS_PHP=yes, MATCHES=yes
```

---

## Voir aussi

- [`ClusterVOCollection`](Collections/ClusterVOCollection.md) - Collection de clusters
- [`ClusterVO`](ValueObjects/ClusterVO.md) - Conteneur de données
- [`ClusterQuery`](ClusterQuery.md) - Moteur de requêtes
- [`AggregateFunctionRegistry`](Registry/AggregateFunctionRegistry.md) - Registre des fonctions