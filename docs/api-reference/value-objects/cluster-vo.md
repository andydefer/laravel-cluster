# ClusterVO - Technical Reference

## Description

Value Object représentant un cluster de données pour l'évaluation des requêtes. Il encapsule une structure de données imbriquée en fournissant à la fois une version aplatie (pour un accès rapide) et une version imbriquée (pour l'intégrité des données).

## Hiérarchie

```
AbstractValueObject
    └── ClusterVO
```

## Rôle principal

Fournit un conteneur de données optimisé pour les opérations de requête avec :
- **Aplatissement automatique** : Conversion des structures imbriquées en notation pointée
- **Accès rapide** : Recherche de valeurs par clé en O(1)
- **Validation stricte** : Types de données autorisés et clés valides
- **Double représentation** : Accès aux données aplaties et imbriquées

---

## API

### `__construct(array $data)`

Initialise un cluster avec des données.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données du cluster |

**Exceptions :** `InvalidArgumentException` - Si les données sont vides ou contiennent des types non supportés

**Exemple :**
```php
$cluster = new ClusterVO([
    'user' => [
        'name' => 'John Doe',
        'age' => 30,
    ],
    'roles' => ['admin', 'editor'],
]);
```

---

### `getValue(): StrictAssociative`

Retourne la représentation aplatie des données.

**Retourne :** `StrictAssociative` - Les données aplaties avec des clés en notation pointée

**Exemple :**
```php
$data = $cluster->getValue();
// ['user.name' => 'John Doe', 'user.age' => 30, 'roles_admin' => 'true', 'roles_editor' => 'true']
```

---

### `getUnflattened(): StrictAssociative`

Retourne la représentation imbriquée (non aplatie) des données. Décode automatiquement les chaînes JSON en tableaux.

**Retourne :** `StrictAssociative` - La structure imbriquée originale avec les JSON décodés

**Exemple :**
```php
$nested = $cluster->getUnflattened();
// ['user' => ['name' => 'John Doe', 'age' => 30], 'roles' => ['admin', 'editor']]
```

---

### `getNestedData(): array`

Retourne la représentation imbriquée sous forme de tableau.

**Retourne :** `array` - La structure imbriquée originale avec les JSON décodés

**Exemple :**
```php
$nestedArray = $cluster->getNestedData();
```

---

### `has(string $key): bool`

Vérifie si une clé existe dans les données aplaties.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé en notation pointée à vérifier |

**Retourne :** `bool` - `true` si la clé existe

**Exemple :**
```php
$exists = $cluster->has('user.name'); // true
$exists = $cluster->has('user.email'); // false
```

---

### `get(string $key, mixed $default = null): mixed`

Récupère une valeur des données aplaties par clé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé en notation pointée |
| `$default` | `mixed` | La valeur par défaut si la clé n'existe pas |

**Retourne :** `mixed` - La valeur ou la valeur par défaut

**Exemple :**
```php
$name = $cluster->get('user.name'); // 'John Doe'
$email = $cluster->get('user.email', 'unknown@example.com'); // 'unknown@example.com'
```

---

### `keys(): array`

Retourne toutes les clés des données aplaties.

**Retourne :** `array<int, string>` - La liste des clés en notation pointée

**Exemple :**
```php
$keys = $cluster->keys();
// ['user.name', 'user.age', 'roles_admin', 'roles_editor']
```

---

### `toArray(): array`

Retourne les données aplaties sous forme de tableau.

**Retourne :** `array<string, int|float|string|null>` - Les données aplaties

**Exemple :**
```php
$array = $cluster->toArray();
```

---

## Types de données supportés

| Type | Support | Commentaire |
|------|---------|-------------|
| `string` | ✅ | Converti en `'true'` ou `'false'` pour les booléens |
| `int` | ✅ | Conservé tel quel |
| `float` | ✅ | Conservé tel quel |
| `bool` | ✅ | Converti en `'true'` ou `'false'` |
| `null` | ✅ | Conservé tel quel |
| `array` | ✅ | Aplati ou JSON encodé selon la structure |
| `object` | ⚠️ | Seuls `stdClass` sont autorisés |
| `resource` | ❌ | Non autorisé |

---

## Cas d'utilisation

### Cas 1 : Création et accès aux données

```php
<?php

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$cluster = new ClusterVO([
    'user' => [
        'name' => 'John Doe',
        'age' => 30,
        'email' => 'john@example.com',
    ],
    'roles' => ['admin', 'editor'],
    'active' => true,
]);

// Accès aux données aplaties
echo $cluster->get('user.name'); // John Doe
echo $cluster->get('user.age'); // 30
echo $cluster->get('roles_admin'); // true
echo $cluster->get('active'); // true

// Vérification d'existence
if ($cluster->has('user.email')) {
    $email = $cluster->get('user.email');
}
```

### Cas 2 : Structures imbriquées complexes

```php
$cluster = new ClusterVO([
    'addresses' => [
        [
            'city' => 'Kinshasa',
            'country' => 'RDC',
            'is_primary' => true,
        ],
        [
            'city' => 'Paris',
            'country' => 'France',
            'is_primary' => false,
        ],
    ],
]);

// Les tableaux imbriqués sont JSON encodés
$addresses = $cluster->get('addresses');
// '[{"city":"Kinshasa","country":"RDC","is_primary":"true"},...]'

// Accès via notation pointée après aplatissement
$hasKinshasa = $cluster->has('addresses_Kinshasa'); // true
```

### Cas 3 : Décodage JSON automatique

```php
$cluster = new ClusterVO([
    'settings' => '{"theme":"dark","notifications":true}',
]);

// getUnflattened décode automatiquement le JSON
$unflattened = $cluster->getUnflattened();
// ['settings' => ['theme' => 'dark', 'notifications' => true]]
```

### Cas 4 : Utilisation avec les requêtes

```php
$cluster = new ClusterVO([
    'status' => 'active',
    'role' => 'admin',
    'addresses' => [
        ['city' => 'Kinshasa'],
        ['city' => 'Paris'],
    ],
]);

// Vérification d'une condition simple
$isActive = $cluster->get('status') === 'active';

// Vérification d'existence d'une clé aplatie
$hasKinshasa = $cluster->has('addresses_Kinshasa');
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Données vides | `InvalidArgumentException` | `Cluster cannot be empty` |
| Clé non string | `InvalidArgumentException` | `Cluster keys must be strings` |
| Objet non stdClass | `InvalidArgumentException` | `Cluster values must be string, int, float, bool, array or null. Got object for key "{key}"` |
| Resource | `InvalidArgumentException` | `Cluster values cannot be resources. Got resource for key "{key}"` |
| Type invalide après aplatissement | `InvalidArgumentException` | `Cluster values must be string, int, float or null after flatten. Got {type} for key "{key}"` |

---

## Performance

- **Construction :** O(n) où n est le nombre de nœuds dans la structure
- **get() :** O(1) - Accès direct au tableau aplati
- **has() :** O(1) - Vérification directe dans le tableau
- **getUnflattened() :** O(n) - Reconstruction de la structure imbriquée
- **Mémoire :** Stocke deux représentations (aplatie et imbriquée)

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

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

// ==================== CRÉATION ====================

$cluster = new ClusterVO([
    'id' => 1,
    'name' => 'John Doe',
    'status' => 'active',
    'role' => 'admin',
    'age' => 30,
    'verified' => true,
    'addresses' => [
        [
            'city' => 'Kinshasa',
            'country' => 'RDC',
            'is_primary' => true,
        ],
        [
            'city' => 'Paris',
            'country' => 'France',
            'is_primary' => false,
        ],
    ],
    'scores' => [85, 90, 78],
    'tags' => ['php', 'js', 'docker'],
]);

// ==================== ACCÈS AUX DONNÉES ====================

// Accès simple
echo "Name: " . $cluster->get('name') . "\n";
echo "Status: " . $cluster->get('status') . "\n";
echo "Age: " . $cluster->get('age') . "\n";
echo "Verified: " . $cluster->get('verified') . "\n"; // 'true'

// Vérification d'existence
if ($cluster->has('addresses')) {
    echo "Has addresses\n";
}

// Clés disponibles
$keys = $cluster->keys();
echo "Keys: " . implode(', ', $keys) . "\n";

// ==================== STRUCTURES APLATIES ET IMBRIQUÉES ====================

// Données aplaties
$flattened = $cluster->toArray();
print_r($flattened);
// [
//     'id' => 1,
//     'name' => 'John Doe',
//     'status' => 'active',
//     'role' => 'admin',
//     'age' => 30,
//     'verified' => 'true',
//     'addresses' => '[{"city":"Kinshasa",...}]',
//     'scores' => '[85,90,78]',
//     'tags' => '["php","js","docker"]',
// ]

// Données imbriquées (avec JSON décodés)
$nested = $cluster->getUnflattened()->toArray();
print_r($nested);
// [
//     'id' => 1,
//     'name' => 'John Doe',
//     'status' => 'active',
//     'role' => 'admin',
//     'age' => 30,
//     'verified' => 'true',
//     'addresses' => [
//         ['city' => 'Kinshasa', 'country' => 'RDC', 'is_primary' => true],
//         ['city' => 'Paris', 'country' => 'France', 'is_primary' => false],
//     ],
//     'scores' => [85, 90, 78],
//     'tags' => ['php', 'js', 'docker'],
// ]

// ==================== UTILISATION AVEC LES REQUÊTES ====================

// Vérification de conditions
$isActive = $cluster->get('status') === 'active';
$isAdmin = $cluster->get('role') === 'admin';
$isAdult = $cluster->get('age') >= 18;

if ($isActive && $isAdmin && $isAdult) {
    echo "User is active admin and adult\n";
}

// Vérification de présence de valeurs dans les tableaux aplatis
$hasPhp = $cluster->has('tags_php'); // true
$hasKinshasa = $cluster->has('addresses_Kinshasa'); // true
```

---

## Voir aussi

- `FlatArrayService` - Service d'aplatissement des tableaux
- `StrictAssociative` - Collection associative stricte
- `ClusterVOCollection` - Collection de clusters
- `ClusterQuery` - Moteur de requêtes