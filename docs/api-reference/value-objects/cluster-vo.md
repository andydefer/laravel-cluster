# ClusterVO - Référence Technique

## Description

Value Object représentant un cluster de données pour l'évaluation de requêtes, avec accès rapide via une structure aplatie et conservation de la structure originale imbriquée.

## Hiérarchie / Implémentations

```
AbstractValueObject
    └── ClusterVO
```

**Interfaces implémentées :**
- Aucune interface explicite (hérite de `AbstractValueObject`)

## Rôle principal

`ClusterVO` encapsule les données d'un cluster en deux représentations : une version aplatie (flat) pour un accès rapide par clés en notation pointée, et une version imbriquée (nested) pour conserver la structure complète. Cette double représentation permet à la fois des recherches efficaces lors de l'évaluation des conditions et l'accès à la structure originale pour les sous-conditions.

---

## API / Méthodes publiques

### `__construct(array $data)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Données du cluster à encapsuler |

**Retourne :** `void`

**Exceptions :** 
- `InvalidArgumentException` si le tableau est vide
- `InvalidArgumentException` si une clé n'est pas une chaîne
- `InvalidArgumentException` si une valeur est un objet non-supporté ou une ressource
- `InvalidArgumentException` si une valeur aplatie est d'un type non-supporté

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$cluster = new ClusterVO([
    'user' => ['name' => 'John', 'age' => 30],
    'roles' => ['admin', 'editor'],
    'status' => 'active'
]);
```

---

### `getValue(): StrictAssociative`

Retourne la représentation aplatie des données du cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `StrictAssociative` - Données aplaties avec clés en notation pointée

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$flat = $cluster->getValue();
// Résultat : [
//   'user.name' => 'John',
//   'user.age' => 30,
//   'roles.0' => 'admin',
//   'roles.1' => 'editor',
//   'status' => 'active'
// ]
```

---

### `getUnflattened(): StrictAssociative`

Retourne la représentation imbriquée (non aplatie) des données du cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `StrictAssociative` - Structure originale imbriquée

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$nested = $cluster->getUnflattened();
// Résultat : [
//   'user' => ['name' => 'John', 'age' => 30],
//   'roles' => ['admin', 'editor'],
//   'status' => 'active'
// ]
```

---

### `getNestedData(): array`

Retourne la représentation imbriquée sous forme de tableau natif PHP.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array` - Structure originale imbriquée

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$nestedArray = $cluster->getNestedData();
// Résultat identique à getUnflattened() mais en array natif
```

---

### `has(string $key): bool`

Vérifie si une clé existe dans les données aplaties.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé en notation pointée à vérifier |

**Retourne :** `bool` - `true` si la clé existe

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$cluster = new ClusterVO(['user' => ['name' => 'John']]);

$hasName = $cluster->has('user.name'); // true
$hasAge = $cluster->has('user.age');   // false
```

---

### `get(string $key, mixed $default = null): mixed`

Récupère une valeur depuis les données aplaties par sa clé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé en notation pointée |
| `$default` | `mixed` | Valeur par défaut si la clé n'existe pas |

**Retourne :** `mixed` - La valeur trouvée ou la valeur par défaut

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$cluster = new ClusterVO(['user' => ['name' => 'John']]);

$name = $cluster->get('user.name'); // 'John'
$age = $cluster->get('user.age', 0); // 0
```

---

### `keys(): array`

Retourne toutes les clés des données aplaties.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, string>` - Liste des clés en notation pointée

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$cluster = new ClusterVO([
    'user' => ['name' => 'John', 'age' => 30],
    'status' => 'active'
]);

$keys = $cluster->keys();
// ['user.name', 'user.age', 'status']
```

---

### `toArray(): array`

Retourne les données aplaties sous forme de tableau natif PHP.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<string, int|float|string|null>` - Données aplaties

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$flatArray = $cluster->toArray();
// [
//   'user.name' => 'John',
//   'user.age' => 30,
//   'status' => 'active'
// ]
```

---

## Cas d'utilisation

### Cas 1 : Accès rapide aux données pour l'évaluation des conditions

Les conditions simples utilisent la représentation aplatie pour un accès O(1).

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

$cluster = new ClusterVO([
    'user' => ['name' => 'John', 'age' => 25],
    'status' => 'active'
]);

// Évaluation d'une condition simple
$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$result = $node->evaluate($cluster); // true

// La condition utilise $cluster->toArray() pour accéder à 'status'
```

### Cas 2 : Navigation dans les structures imbriquées

Les sous-conditions utilisent la structure imbriquée pour naviguer dans les tableaux.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

$cluster = new ClusterVO([
    'addresses' => [
        ['city' => 'kinshasa', 'country' => 'rdc'],
        ['city' => 'paris', 'country' => 'france']
    ]
]);

// Évaluation d'une sous-condition
$sub = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);

// La sous-condition utilise $cluster->getUnflattened() pour naviguer dans le tableau
$result = $sub->evaluate($cluster); // true
```

### Cas 3 : Recherche par chemin exact

Utilisation directe de l'API pour accéder à des données spécifiques.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$cluster = new ClusterVO([
    'settings' => [
        'notifications' => [
            'email' => 'true',
            'sms' => 'false'
        ],
        'timezone' => 'UTC'
    ]
]);

// Accès à une valeur profonde
$emailNotifications = $cluster->get('settings.notifications.email'); // 'true'

// Vérification d'existence
$hasSms = $cluster->has('settings.notifications.sms'); // true

// Récupération avec valeur par défaut
$push = $cluster->get('settings.notifications.push', 'false'); // 'false'
```

### Cas 4 : Itération sur les clés

Parcourir toutes les propriétés du cluster pour un traitement personnalisé.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$cluster = new ClusterVO([
    'name' => 'John',
    'age' => '30',
    'status' => 'active',
    'score' => '85.5'
]);

$keys = $cluster->keys();
// ['name', 'age', 'status', 'score']

// Traitement personnalisé
$formatted = [];
foreach ($keys as $key) {
    $value = $cluster->get($key);
    if (is_numeric($value)) {
        $formatted[$key] = (float) $value;
    } else {
        $formatted[$key] = strtoupper($value);
    }
}
// ['name' => 'JOHN', 'age' => 30.0, 'status' => 'ACTIVE', 'score' => 85.5]
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Tableau vide | `InvalidArgumentException` | `Cluster cannot be empty` |
| Clé non-string | `InvalidArgumentException` | `Cluster keys must be strings` |
| Valeur objet non-stdClass | `InvalidArgumentException` | `Cluster values must be string, int, float, bool, array or null. Got object for key "{key}"` |
| Valeur ressource | `InvalidArgumentException` | `Cluster values cannot be resources. Got resource for key "{key}"` |
| Type invalide après flatten | `InvalidArgumentException` | `Cluster values must be string, int, float or null after flatten. Got {type} for key "{key}"` |

**Note :** Les tableaux contenant des booléens sont supportés et convertis en `int` (true → 1, false → 0) lors du flatten.

---

## Intégration

### Avec `ConditionNode`
```php
<?php

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;

$cluster = new ClusterVO(['status' => 'active']);
$node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');

// La condition utilise $cluster->toArray() pour accéder aux données
$result = $node->evaluate($cluster); // true
```

### Avec `SubConditionNode`
```php
<?php

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;

$cluster = new ClusterVO(['addresses' => [['city' => 'kinshasa']]]);
$sub = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);

// La sous-condition utilise $cluster->getUnflattened() pour naviguer
$result = $sub->evaluate($cluster); // true
```

### Avec `ClusterQuery`
```php
<?php

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;

$collection = new ClusterVOCollection([
    new ClusterVO(['status' => 'active']),
    new ClusterVO(['status' => 'inactive'])
]);

$query = new ClusterQuery();
$result = $query->filter($collection, 'status=active');
// Les ClusterVO sont évalués par les nœuds de l'AST
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `__construct()` | **O(n)** | Parcourt toutes les données pour le flatten |
| `get()` / `has()` | **O(1)** | Accès direct au tableau aplati |
| `toArray()` | **O(1)** | Retourne le tableau aplati (sans copie) |
| `getUnflattened()` | **O(1)** | Retourne la structure imbriquée (sans copie) |
| `keys()` | **O(n)** | Récupère toutes les clés du tableau aplati |
| Mémoire | **O(n)** | Stocke deux représentations des données |

**Optimisations :**
- **Flatten à la construction** : Le travail est effectué une fois, les accès sont ensuite O(1)
- **Double stockage** : Les deux représentations sont conservées pour répondre aux différents besoins
- **Validation rigoureuse** : Les données sont validées à la construction, évitant les erreurs à l'exécution
- **Utilisation de StrictAssociative** : Valeurs immutables, thread-safe

**Considérations :**
- La double représentation double l'utilisation mémoire (mais les données sont généralement petites)
- Le flatten est effectué pour toutes les données, même si l'on n'utilise que la structure imbriquée
- Les tableaux profonds créent de nombreuses clés en notation pointée (ex: `a.b.c.d.e`)

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (utilise PHP 8+ uniquement) |

**Dépendances :**
- `AbstractValueObject` de `andydefer/domain-structures`
- `StrictAssociative` pour les collections immutables
- `FlatArrayService` pour l'aplatissement des tableaux

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;

// 1. Création d'un ClusterVO avec des données complexes
$cluster = new ClusterVO([
    'id' => 42,
    'name' => 'Cluster Alpha',
    'status' => 'active',
    'score' => 95.5,
    'metadata' => [
        'created_at' => '2024-01-15',
        'updated_at' => '2024-01-20',
        'tags' => ['production', 'critical', 'v3']
    ],
    'addresses' => [
        [
            'city' => 'kinshasa',
            'country' => 'rdc',
            'postal_code' => '1001'
        ],
        [
            'city' => 'paris',
            'country' => 'france',
            'postal_code' => '75001'
        ]
    ],
    'settings' => [
        'notifications' => [
            'email' => 'true',
            'sms' => 'false'
        ],
        'timezone' => 'UTC+1'
    ]
]);

// 2. Accès direct aux données
echo "Name: " . $cluster->get('name'); // "Cluster Alpha"
echo "Status: " . $cluster->get('status'); // "active"
echo "City: " . $cluster->get('addresses.0.city'); // "kinshasa"
echo "Email notifications: " . $cluster->get('settings.notifications.email'); // "true"

// 3. Vérification d'existence
if ($cluster->has('metadata.tags.2')) {
    echo "Third tag: " . $cluster->get('metadata.tags.2'); // "v3"
}

// 4. Récupération des clés
$keys = $cluster->keys();
echo "Total properties: " . count($keys); // Beaucoup de clés (flatten)

// 5. Évaluation d'une condition simple
$statusCondition = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
$isActive = $statusCondition->evaluate($cluster); // true

// 6. Évaluation d'une condition d'existence
$hasEmail = new ConditionNode('settings.notifications.email', ComparisonOperator::EXISTS);
$exists = $hasEmail->evaluate($cluster); // true

// 7. Évaluation d'une sous-condition sur tableau
$subCondition = new SubConditionNode(
    'addresses',
    new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa')
);
$hasKinshasa = $subCondition->evaluate($cluster); // true

// 8. Évaluation d'une sous-condition avec condition composite
$cityCondition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
$countryCondition = new ConditionNode('country', ComparisonOperator::EQUAL, 'rdc');
$andGroup = new GroupNode(LogicalOperator::AND, $cityCondition, $countryCondition);

$subComplex = new SubConditionNode('addresses', $andGroup);
$hasKinshasaRDC = $subComplex->evaluate($cluster); // true

// 9. Évaluation d'une condition sur chemin profond
$deepCondition = new ConditionNode(
    'settings.notifications.email',
    ComparisonOperator::EQUAL,
    'true'
);
$emailEnabled = $deepCondition->evaluate($cluster); // true

// 10. Utilisation avec collection
$collection = new ClusterVOCollection([$cluster]);
$filtered = $collection->filter(
    fn (ClusterVO $c) => $c->get('status') === 'active' && $c->get('score') > 90
);
// Retourne les clusters actifs avec score > 90

// 11. Accès à la structure originale
$nested = $cluster->getUnflattened()->toArray();
print_r($nested);
// [
//     'id' => 42,
//     'name' => 'Cluster Alpha',
//     'status' => 'active',
//     ...
//     'addresses' => [
//         ['city' => 'kinshasa', ...],
//         ['city' => 'paris', ...]
//     ]
// ]

// 12. Extraction de données avec traitement
$tags = [];
if ($cluster->has('metadata.tags')) {
    $tags = $cluster->get('metadata.tags');
    // Si c'est un tableau, il sera sous forme de string
    // Dans ce cas, on utilise la structure nested
    $nestedData = $cluster->getNestedData();
    if (isset($nestedData['metadata']['tags'])) {
        $tags = $nestedData['metadata']['tags']; // ['production', 'critical', 'v3']
    }
}
```

---

## Voir aussi

- `AbstractValueObject` - Classe de base pour les Value Objects
- `StrictAssociative` - Collection immutables pour données associatives
- `FlatArrayService` - Service d'aplatissement de tableaux
- `ConditionNode` - Nœud de condition utilisant l'API de ClusterVO
- `SubConditionNode` - Sous-condition naviguant dans la structure imbriquée
- `ClusterVOCollection` - Collection de ClusterVO