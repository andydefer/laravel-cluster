# ClusterVO - Référence Technique

## Description

Value Object représentant un cluster de données avec des capacités de normalisation et d'aplatissement automatiques. Il encapsule des données structurées sous forme de tableau en garantissant l'intégrité des types et en fournissant une API immuable pour l'accès aux données.

## Hiérarchie

```
AbstractValueObject
    └── ClusterVO
```

**Interfaces :** Aucune (hérite de `AbstractValueObject`)

## Rôle principal

`ClusterVO` est le conteneur de données central du package. Il offre :

- **Validation stricte** : Vérification des types à la construction
- **Aplatissement automatique** : Transformation des structures imbriquées en données plates
- **Accès immuable** : API en lecture seule via `get()` et `has()`
- **Double représentation** : Conservation simultanée des versions plate et non-plate des données
- **Intégration avec le filtrage** : Données prêtes à être utilisées par les nœuds de condition

Cette classe est conçue pour être utilisée comme source de données dans les opérations de filtrage et de requêtage.

---

## API / Méthodes publiques

### `__construct(array $value)`

Initialise un cluster avec les données fournies.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `array<string, mixed>` | Données du cluster |

**Exceptions :** `InvalidArgumentException` - Si les données sont invalides

**Validations effectuées :**
- Le tableau ne doit pas être vide
- Les clés doivent être des chaînes
- Les valeurs ne doivent pas être des objets (sauf `stdClass`) ou des ressources
- Les types sont validés après aplatissement

**Exemple :**
```php
$cluster = new ClusterVO([
    'id' => 1,
    'name' => 'John Doe',
    'active' => true,
    'tags' => ['php', 'js'],
    'address' => [
        'city' => 'Paris',
        'zip' => 75000
    ]
]);
```

---

### `getValue(): StrictAssociative`

Retourne les données du cluster sous forme aplatie.

**Retourne :** `StrictAssociative` - Données aplaties du cluster

**Exemple :**
```php
$flat = $cluster->getValue();
// Résultat :
// [
//     'id' => 1,
//     'name' => 'John Doe',
//     'active' => 'true',
//     'tags_php' => 'true',
//     'tags_js' => 'true',
//     'address.city' => 'Paris',
//     'address.zip' => 75000
// ]
```

---

### `getUnflattened(): StrictAssociative`

Retourne les données du cluster sous forme non-apiatie (structure originale).

**Retourne :** `StrictAssociative` - Données originales du cluster

**Exemple :**
```php
$original = $cluster->getUnflattened();
// Résultat :
// [
//     'id' => 1,
//     'name' => 'John Doe',
//     'active' => 'true',
//     'tags' => ['php', 'js'],
//     'address' => [
//         'city' => 'Paris',
//         'zip' => 75000
//     ]
// ]
```

---

### `has(string $key): bool`

Vérifie si une clé existe dans les données aplaties.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé à vérifier (notation pointée) |

**Retourne :** `bool` - `true` si la clé existe, `false` sinon

**Exemple :**
```php
if ($cluster->has('address.city')) {
    echo "La ville est définie";
}

// Fonctionne également avec les clés expansées
if ($cluster->has('tags_php')) {
    echo "Le tag 'php' est présent";
}
```

---

### `get(string $key, mixed $default = null): mixed`

Récupère une valeur des données aplaties.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé à récupérer (notation pointée) |
| `$default` | `mixed` | Valeur par défaut si la clé n'existe pas |

**Retourne :** `mixed` - Valeur de la clé ou la valeur par défaut

**Exemple :**
```php
$city = $cluster->get('address.city', 'Inconnue');
$age = $cluster->get('age', 0);
$hasPhp = $cluster->get('tags_php', false);
```

---

### `keys(): array`

Retourne toutes les clés des données aplaties.

**Retourne :** `array<int, string>` - Tableau des clés

**Exemple :**
```php
$keys = $cluster->keys();
// Résultat : ['id', 'name', 'active', 'tags_php', 'tags_js', 'address.city', 'address.zip']
```

---

### `toArray(): array`

Retourne les données aplaties sous forme de tableau.

**Retourne :** `array<string, int|float|string|null>` - Données aplaties

**Exemple :**
```php
$array = $cluster->toArray();
// Identique à $cluster->getValue()->toArray()
```

---

### Méthodes privées

| Méthode | Rôle |
|---------|------|
| `validateInput()` | Valide les types des données en entrée |
| `validateFlattened()` | Valide les types après aplatissement |

---

## Cas d'utilisation

### Cas 1 : Création et accès aux données

Utilisation basique du cluster.

```php
<?php

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$cluster = new ClusterVO([
    'id' => 123,
    'user' => [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ],
    'roles' => ['admin', 'user'],
    'active' => true
]);

// Accès direct
echo $cluster->get('user.name'); // "John Doe"
echo $cluster->get('roles_admin'); // "true"

// Vérification d'existence
if ($cluster->has('user.email')) {
    echo $cluster->get('user.email');
}

// Liste des clés
print_r($cluster->keys());
// ['id', 'user.name', 'user.email', 'roles_admin', 'roles_user', 'active']
```

---

### Cas 2 : Filtrage avec les clusters

Utilisation dans un contexte de filtrage.

```php
<?php

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;

// Création des clusters
$clusters = [
    new ClusterVO(['id' => 1, 'age' => 25, 'status' => 'active']),
    new ClusterVO(['id' => 2, 'age' => 17, 'status' => 'inactive']),
    new ClusterVO(['id' => 3, 'age' => 30, 'status' => 'active']),
];

// Filtrage
$condition = new ConditionNode(
    key: 'age',
    operator: ComparisonOperator::GREATER_THAN_OR_EQUAL,
    value: '18'
);

$filtered = array_filter(
    $clusters,
    fn($cluster) => $condition->evaluate($cluster)
);

foreach ($filtered as $cluster) {
    echo $cluster->get('id') . PHP_EOL;
}
// Résultat : 1, 3
```

---

### Cas 3 : Stockage en base de données

Préparer les données pour le stockage.

```php
<?php

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$cluster = new ClusterVO([
    'id' => 456,
    'metadata' => [
        'created_at' => '2024-01-01',
        'updated_at' => '2024-01-15'
    ],
    'tags' => ['php', 'laravel']
]);

// Données à stocker en JSON
$jsonData = json_encode($cluster->getUnflattened()->toArray());
// {"id":456,"metadata":{"created_at":"2024-01-01","updated_at":"2024-01-15"},"tags":["php","laravel"]}

// Ou données plates pour indexation
$flatData = $cluster->toArray();
// ['id' => 456, 'metadata.created_at' => '2024-01-01', 'metadata.updated_at' => '2024-01-15', 'tags_php' => 'true', 'tags_laravel' => 'true']
```

---

### Cas 4 : Conversion depuis une requête API

Créer des clusters à partir de données externes.

```php
<?php

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

class ApiClient
{
    public function getUserClusters(): array
    {
        $response = $this->fetchFromApi('/users');

        return array_map(
            fn($user) => new ClusterVO([
                'user_id' => $user['id'],
                'profile' => [
                    'name' => $user['name'],
                    'email' => $user['email']
                ],
                'roles' => $user['roles'] ?? [],
                'active' => $user['active'] ?? false
            ]),
            $response['data']
        );
    }
}
```

---

### Cas 5 : Validation des données

Utiliser la validation intégrée.

```php
<?php

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

try {
    // ✅ Données valides
    $cluster = new ClusterVO([
        'id' => 1,
        'name' => 'John',
        'active' => true
    ]);

    // ❌ Données invalides - objet
    $cluster = new ClusterVO([
        'id' => 1,
        'date' => new DateTime() // Objet non supporté
    ]);
} catch (InvalidArgumentException $e) {
    echo "Erreur : " . $e->getMessage();
    // "Cluster values must be string, int, float, bool, array or null. Got object for key "date""
}

// ❌ Données invalides - ressources
try {
    $cluster = new ClusterVO([
        'file' => fopen('/tmp/file.txt', 'r')
    ]);
} catch (InvalidArgumentException $e) {
    echo "Erreur : " . $e->getMessage();
    // "Cluster values cannot be resources. Got resource for key "file""
}

// ❌ Données invalides - tableau vide
try {
    $cluster = new ClusterVO([]);
} catch (InvalidArgumentException $e) {
    echo "Erreur : " . $e->getMessage();
    // "Cluster cannot be empty"
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Tableau vide | `InvalidArgumentException` | `Cluster cannot be empty` |
| Clé non-string | `InvalidArgumentException` | `Cluster keys must be strings` |
| Valeur de type objet | `InvalidArgumentException` | `Cluster values must be string, int, float, bool, array or null. Got object for key "{key}"` |
| Valeur de type resource | `InvalidArgumentException` | `Cluster values cannot be resources. Got resource for key "{key}"` |
| Valeur non supportée après flatten | `InvalidArgumentException` | `Cluster values must be string, int, float or null after flatten. Got {type} for key "{key}"` |

### Types autorisés

| Type | Accepté en entrée | Après flatten |
|------|-------------------|---------------|
| `string` | ✅ | ✅ |
| `int` | ✅ | ✅ |
| `float` | ✅ | ✅ |
| `bool` | ✅ | Converti en `'true'`/`'false'` |
| `null` | ✅ | ✅ |
| `array` | ✅ | Expansé en clés plates |
| `object` (stdClass) | ⚠️ | Désérialisé si compatible |
| `object` (autre) | ❌ | N/A |
| `resource` | ❌ | N/A |

---

## Intégration

`ClusterVO` s'intègre avec :

- **`FlatArrayService`** : Service d'aplatissement des données
- **`StrictAssociative`** : Conteneur typé pour les données
- **`AbstractValueObject`** : Classe parente pour les Value Objects
- **`ConditionNode`** : Évaluation des conditions sur les clusters
- **`ClusterService`** : Filtrage des clusters

Cette classe est utilisée par :

- **`ClusterService`** : Comme source de données pour le filtrage
- **`ClusterVOCollection`** : Comme élément de collection
- **Les nœuds de condition** : Pour l'évaluation des expressions

---

## Performance

### Complexité algorithmique

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `__construct()` | O(n) | n = nombre total d'éléments (aplatissement) |
| `get()` | O(1) | Accès direct au tableau aplati |
| `has()` | O(1) | Vérification directe |
| `keys()` | O(n) | Extraction des clés |
| `toArray()` | O(1) | Retour direct du tableau |
| `getUnflattened()` | O(1) | Retour direct des données non-apiaties |

### Optimisations

- Aplatissement effectué une seule fois à la construction
- Double stockage (plat + non-plat) pour des accès rapides
- Pas de calculs répétés
- Utilisation de `StrictAssociative` pour un accès sécurisé

### Considérations mémoire

- Double stockage des données (version plate + version non-plate)
- Peut consommer plus de mémoire pour de très grandes structures
- L'expansion des tableaux indexés peut augmenter le nombre de clés

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (nécessite PHP 8.0+) |

**Dépendances :**
- `FlatArrayService` - Service d'aplatissement
- `StrictAssociative` - Conteneur de données typé
- `AbstractValueObject` - Classe parente

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\ClusterQuery;

// 1. Création d'un cluster simple
$user = new ClusterVO([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'active' => true,
    'roles' => ['admin', 'user'],
    'preferences' => [
        'theme' => 'dark',
        'language' => 'fr',
        'notifications' => true
    ]
]);

// 2. Accès aux données aplaties
echo "Nom : " . $user->get('name') . PHP_EOL;
echo "Âge : " . $user->get('age') . PHP_EOL;
echo "Thème : " . $user->get('preferences.theme') . PHP_EOL;
echo "Rôle admin : " . ($user->get('roles_admin') ? 'Oui' : 'Non') . PHP_EOL;

// 3. Vérification d'existence
if ($user->has('preferences.notifications')) {
    $notifications = $user->get('preferences.notifications');
    echo "Notifications : " . ($notifications === 'true' ? 'Activées' : 'Désactivées') . PHP_EOL;
}

// 4. Liste des clés
echo "\nClés disponibles :\n";
foreach ($user->keys() as $key) {
    echo "- {$key}: " . var_export($user->get($key), true) . PHP_EOL;
}

// 5. Accès aux données originales
$original = $user->getUnflattened()->toArray();
echo "\nStructure originale :\n";
print_r($original);

// 6. Conversion en collection
$collection = new ClusterVOCollection();
$collection->add($user);

// 7. Filtrage avec ClusterService
$service = new ClusterService(new ClusterQuery());
$filtered = $service->filter(
    $collection,
    'active = "true" AND age >= 18 AND preferences.theme = "dark"'
);

echo "\nClusters filtrés : " . $filtered->count() . PHP_EOL;

// 8. Gestion des erreurs
try {
    // Cluster invalide avec objet
    $invalid = new ClusterVO([
        'id' => 2,
        'date' => new DateTime('now')
    ]);
} catch (InvalidArgumentException $e) {
    echo "\nErreur capturée : " . $e->getMessage() . PHP_EOL;
}

// 9. Création en masse
$usersData = [
    ['id' => 1, 'name' => 'Alice', 'age' => 25, 'active' => true],
    ['id' => 2, 'name' => 'Bob', 'age' => 17, 'active' => false],
    ['id' => 3, 'name' => 'Charlie', 'age' => 30, 'active' => true],
];

$clusters = array_map(
    fn($data) => new ClusterVO($data),
    $usersData
);

echo "\nClusters créés : " . count($clusters) . PHP_EOL;

// 10. Sérialisation
$json = json_encode($original);
echo "\nJSON : " . $json . PHP_EOL;

// 11. Désérialisation
$decoded = json_decode($json, true);
$reconstructed = new ClusterVO($decoded);
echo "Reconstruction : " . $reconstructed->get('name') . PHP_EOL;
```

---

## Voir aussi

- `FlatArrayService` - Service d'aplatissement des données
- `StrictAssociative` - Conteneur de données typé
- `AbstractValueObject` - Classe parente
- `ClusterVOCollection` - Collection de clusters
- `ClusterService` - Service de filtrage
- `ConditionNode` - Évaluation des conditions