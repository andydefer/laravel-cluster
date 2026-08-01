# FlatArrayService - Référence Technique

## Description

Service de normalisation des tableaux imbriqués qui permet de transformer des structures complexes en représentations plates et inversement. Il gère spécifiquement l'expansion des tableaux indexés en clés séparées avec des valeurs booléennes.

## Hiérarchie

```
FlatArrayService
```

**Interfaces :** Aucune (classe finale)

## Rôle principal

`FlatArrayService` est un service utilitaire qui permet de :

- **Aplatir (flatten)** : Transformer des tableaux imbriqués en structure plate en utilisant la notation pointée pour les clés et en expansant les tableaux indexés en clés séparées (ex: `tags = ['php', 'js']` → `tags_php = 'yes'`, `tags_js = 'yes'`)
- **Dé-aplatir (unflatten)** : Reconstruire la structure imbriquée à partir d'un tableau plat

Ce service est essentiel pour la recherche et le filtrage sur des données JSON stockées en base de données, où les tableaux indexés doivent être convertis en champs consultables.

---

## API / Méthodes publiques

### `flatten(array $array, string $prefix = ''): array`

Aplatit un tableau imbriqué en une structure plate.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$array` | `array<string, mixed>` | Tableau à aplatir |
| `$prefix` | `string` | Préfixe pour les clés (utilisé pour la récursion) |

**Retourne :** `array<string, int|float|string|null>` - Tableau aplati

**Exceptions :** `InvalidArgumentException` - Si un booléen ou un type de valeur non supporté est rencontré

**Règles de conversion :**
- Les clés sont concaténées avec un point (`.`)
- Les tableaux associatifs sont parcourus récursivement
- Les tableaux indexés sont expansés : `['php', 'js']` → `['tags_php' => 'yes', 'tags_js' => 'yes']`
- **Les booléens sont strictement interdits** (lèvent une exception)
- `null` est conservé comme `null`
- Les tableaux vides indexés → `['key' => null]`

**Exemple :**
```php
$service = new FlatArrayService();

$input = [
    'id' => 1,
    'address' => [
        'city' => 'Paris',
        'zip' => 75000
    ],
    'tags' => ['php', 'js', 'docker'],
    'active' => 'yes',
    'metadata' => null
];

$flat = $service->flatten($input);
// Résultat :
// [
//     'id' => 1,
//     'address.city' => 'Paris',
//     'address.zip' => 75000,
//     'tags_php' => 'yes',
//     'tags_js' => 'yes',
//     'tags_docker' => 'yes',
//     'active' => 'yes',
//     'metadata' => null
// ]
```

---

### `unflatten(array $flat): array`

Reconstruit un tableau imbriqué à partir d'une structure plate.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$flat` | `array<string, int|float|string|null>` | Tableau plat |

**Retourne :** `array<string, mixed>` - Tableau imbriqué reconstruit

**Exemple :**
```php
$flat = [
    'address.city' => 'Paris',
    'address.zip' => 75000,
    'tags_php' => 'yes',
    'tags_js' => 'yes'
];

$nested = $service->unflatten($flat);
// Résultat :
// [
//     'address' => [
//         'city' => 'Paris',
//         'zip' => 75000
//     ],
//     'tags' => ['php', 'js']
// ]
```

---

### Méthodes privées

| Méthode | Rôle |
|---------|------|
| `setNestedValue()` | Définit une valeur dans un tableau imbriqué en utilisant la notation pointée |
| `expandIndexedArray()` | Expande un tableau indexé en clés séparées avec valeur `'yes'` |
| `normalizeValueForKey()` | Normalise une valeur pour l'utiliser comme suffixe de clé |
| `isAssociativeArray()` | Vérifie si un tableau est associatif (clés non numériques) |
| `validateNoBooleans()` | Valide qu'un tableau ne contient pas de booléens |

---

## Cas d'utilisation

### Cas 1 : Aplatir des données utilisateur pour la recherche

Préparer des données pour une recherche en base de données.

```php
<?php

use AndyDefer\LaravelCluster\Services\FlatArrayService;

$service = new FlatArrayService();

$user = [
    'id' => 123,
    'profile' => [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'age' => 30
    ],
    'skills' => ['php', 'javascript', 'docker'],
    'preferences' => [
        'theme' => 'dark',
        'notifications' => 'yes'
    ]
];

$flatUser = $service->flatten($user);
// Résultat :
// [
//     'id' => 123,
//     'profile.first_name' => 'John',
//     'profile.last_name' => 'Doe',
//     'profile.age' => 30,
//     'skills_php' => 'yes',
//     'skills_javascript' => 'yes',
//     'skills_docker' => 'yes',
//     'preferences.theme' => 'dark',
//     'preferences.notifications' => 'yes'
// ]

// Maintenant facile à stocker ou rechercher
$searchFields = array_keys($flatUser);
echo "Champs recherchables : " . implode(', ', $searchFields);
```

---

### Cas 2 : Expansion de tags pour filtrage

Préparer des tags pour des requêtes de filtrage efficaces.

```php
<?php

$service = new FlatArrayService();

$data = [
    'title' => 'Mon article',
    'tags' => ['php', 'laravel', 'api']
];

$flat = $service->flatten($data);
// Résultat :
// [
//     'title' => 'Mon article',
//     'tags_php' => 'yes',
//     'tags_laravel' => 'yes',
//     'tags_api' => 'yes'
// ]

// Permet une recherche simple sur les tags
if (isset($flat['tags_php'])) {
    echo "L'article a le tag 'php'";
}
```

---

### Cas 3 : Dé-aplatir des données de base de données

Reconstruire la structure originale à partir de données plates.

```php
<?php

use AndyDefer\LaravelCluster\Services\FlatArrayService;

$service = new FlatArrayService();

// Données plates provenant de la base de données
$flatData = [
    'user_id' => 456,
    'user_profile.first_name' => 'Jane',
    'user_profile.last_name' => 'Smith',
    'user_profile.age' => 28,
    'user_roles_php' => 'yes',
    'user_roles_csharp' => 'yes'
];

$nested = $service->unflatten($flatData);
// Résultat :
// [
//     'user_id' => 456,
//     'user_profile' => [
//         'first_name' => 'Jane',
//         'last_name' => 'Smith',
//         'age' => 28
//     ],
//     'user_roles' => ['php', 'csharp']
// ]

// Accès facilité aux données
echo $nested['user_profile']['first_name']; // "Jane"
```

---

### Cas 4 : Nettoyage et normalisation des données

Utiliser le normalizer intégré pour nettoyer les données.

```php
<?php

$service = new FlatArrayService();

$dirtyData = [
    'name' => '  John Doe  ',
    'email' => 'JOHN@EXAMPLE.COM',
    'tags' => ['  PHP  ', '  Laravel  ']
];

// Le normalizer nettoye automatiquement
$flat = $service->flatten($dirtyData);
// Résultat :
// [
//     'name' => 'John Doe',
//     'email' => 'john@example.com',
//     'tags_php' => 'yes',
//     'tags_laravel' => 'yes'
// ]
```

---

### Cas 5 : Gestion des tableaux vides

Comportement avec des tableaux vides.

```php
<?php

$service = new FlatArrayService();

$data = [
    'id' => 1,
    'empty_tags' => [],
    'empty_profile' => []
];

$flat = $service->flatten($data);
// Résultat :
// [
//     'id' => 1,
//     'empty_tags' => null,
//     'empty_profile' => null
// ]

// Les tableaux vides indexés deviennent null
// Les tableaux associatifs vides deviennent null
```

---

### Cas 6 : Gestion des booléens (Exception)

Les booléens sont strictement interdits et lèvent une exception.

```php
<?php

$service = new FlatArrayService();

try {
    $data = [
        'id' => 1,
        'active' => true,  // ❌ Booléen interdit
        'verified' => false // ❌ Booléen interdit
    ];
    $flat = $service->flatten($data);
} catch (InvalidArgumentException $e) {
    echo "Erreur : " . $e->getMessage();
    // Boolean values are not allowed. Got bool for key "active"
}

// Utiliser 'yes'/'no' à la place
$data = [
    'id' => 1,
    'active' => 'yes',
    'verified' => 'no'
];
$flat = $service->flatten($data);
// Résultat : ['id' => 1, 'active' => 'yes', 'verified' => 'no']
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Booléen rencontré | `InvalidArgumentException` | `Boolean values are not allowed. Got bool for key "{key}"` |
| Booléen dans tableau indexé | `InvalidArgumentException` | `Boolean values are not allowed in indexed arrays. Found bool in array "{key}"` |
| Type de valeur non supporté | `InvalidArgumentException` | `Unsupported value type "{type}" for key "{key}"` |
| Erreur de normalisation | `InvalidArgumentException` | Message de l'erreur originale |

### Types supportés

| Type | Comportement |
|------|-------------|
| `string` | Conservé tel quel |
| `int` | Conservé comme int |
| `float` | Conservé comme float |
| `bool` | ❌ **Exception** - Non autorisé |
| `null` | Conservé comme `null` |
| Tableau associatif | Parcouru récursivement |
| Tableau indexé (liste) | Expansé en clés séparées avec `'yes'` |
| Tableau vide | Converti en `null` |
| `object` | ❌ Non supporté |

---

## Intégration

`FlatArrayService` s'intègre avec :

- **`normalizer_chain()`** : Fonction de normalisation des données (nettoyage des chaînes)
- **`ClusterVO`** : Utilisé pour aplatir les données des clusters
- **`ClusterVOCollection`** : Utilisé pour les opérations de filtrage sur données aplaties

Ce service est typiquement utilisé dans :

- **Les services de filtrage** : Pour préparer les données à la recherche
- **Les indexeurs de recherche** : Pour indexer des données complexes
- **Les exportateurs de données** : Pour normaliser les structures
- **Les services de validation** : Pour vérifier la structure des données

---

## Performance

### Complexité algorithmique

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `flatten()` | O(n) | n = nombre total d'éléments (y compris les sous-tableaux) |
| `unflatten()` | O(n) | n = nombre de clés plates |
| `expandIndexedArray()` | O(n) | n = taille du tableau indexé |
| `isAssociativeArray()` | O(k) | k = nombre de clés du tableau (peut être O(1) pour les tableaux indexés) |
| `validateNoBooleans()` | O(n) | n = nombre d'éléments du tableau |

### Optimisations

- Récursion pour les tableaux associatifs
- Expansion directe pour les tableaux indexés
- Utilisation de `normalizer_chain()` pour le nettoyage
- Validation des booléens en temps réel

### Considérations mémoire

- La taille du tableau plat peut être plus grande que le tableau original (expansion des tableaux indexés)
- La récursion peut augmenter l'utilisation de la pile pour des structures très imbriquées (> 100 niveaux)

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (nécessite PHP 8.0+) |

**Dépendances :**
- `normalizer_chain()` - Fonction de normalisation de données
- Aucune autre dépendance externe

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Services\FlatArrayService;

// 1. Instanciation
$service = new FlatArrayService();

// 2. Données complexes avec valeurs 'yes'/'no'
$complexData = [
    'id' => 1,
    'name' => '  John Doe  ',
    'email' => 'JOHN@EXAMPLE.COM',
    'active' => 'yes',
    'age' => 30,
    'height' => 1.75,
    'metadata' => null,
    'address' => [
        'street' => '  123 Main St  ',
        'city' => '  New York  ',
        'zip' => 10001,
        'country' => 'USA'
    ],
    'skills' => ['php', 'javascript', 'docker'],
    'preferences' => [
        'theme' => 'dark',
        'notifications' => 'yes',
        'language' => 'fr'
    ],
    'empty_tags' => [],
    'empty_profile' => []
];

echo "=== DONNÉES ORIGINALES ===\n";
print_r($complexData);

// 3. Aplatissement
echo "\n=== DONNÉES APLATIES ===\n";
$flat = $service->flatten($complexData);
print_r($flat);

// 4. Dé-aplatissement
echo "\n=== DONNÉES RECONSTRUITES ===\n";
$nested = $service->unflatten($flat);
print_r($nested);

// 5. Vérification de l'intégrité
echo "\n=== VÉRIFICATION D'INTÉGRITÉ ===\n";
$isEqual = $nested === $service->unflatten($service->flatten($complexData));
echo $isEqual ? "✅ Les données sont intactes" : "❌ Les données ont été modifiées";
echo PHP_EOL;

// 6. Utilisation pour la recherche
echo "\n=== RECHERCHE SUR DONNÉES APLATIES ===\n";
$searchField = 'skills_php';
if (isset($flat[$searchField])) {
    echo "✅ Le champ '{$searchField}' existe avec la valeur '{$flat[$searchField]}'" . PHP_EOL;
}

// 7. Recherche par préfixe
echo "\n=== RECHERCHE PAR PRÉFIXE (skills_) ===\n";
$skillsFields = array_filter(
    array_keys($flat),
    fn($key) => str_starts_with($key, 'skills_')
);
foreach ($skillsFields as $field) {
    echo "- {$field}: {$flat[$field]}\n";
}

// 8. Gestion des erreurs avec booléen
echo "\n=== GESTION DES ERREURS (BOOLÉEN) ===\n";
try {
    $invalid = $service->flatten([
        'valid' => 'ok',
        'invalid' => true // Booléen interdit
    ]);
} catch (InvalidArgumentException $e) {
    echo "✅ Erreur capturée : " . $e->getMessage() . PHP_EOL;
}

// 9. Expansion de tableaux indexés
echo "\n=== EXPANSION DE TABLEAUX INDEXÉS ===\n";
$listData = [
    'tags' => ['php', 'js', 'docker'],
    'roles' => ['admin', 'user']
];
$flatList = $service->flatten($listData);
print_r($flatList);
// Résultat :
// [
//     'tags_php' => 'yes',
//     'tags_js' => 'yes',
//     'tags_docker' => 'yes',
//     'roles_admin' => 'yes',
//     'roles_user' => 'yes'
// ]

// 10. Utilisation avec ClusterVO
echo "\n=== UTILISATION AVEC CLUSTERVO ===\n";
$cluster = new ClusterVO([
    'id' => 1,
    'user' => [
        'name' => 'John',
        'email' => 'john@example.com'
    ],
    'tags' => ['php', 'laravel'],
    'verified' => 'yes'
]);

$clusterFlat = $service->flatten($cluster->toArray());
echo "Clés aplaties du cluster : " . implode(', ', array_keys($clusterFlat)) . PHP_EOL;
// Résultat : id, user.name, user.email, tags_php, tags_laravel, verified
```

---

## Voir aussi

- `ClusterVO` - Objet de données utilisant le flatten
- `ClusterVOCollection` - Collection de clusters
- `normalizer_chain()` - Fonction de normalisation des données
- `ClusterService` - Service de filtrage utilisant le flatten
