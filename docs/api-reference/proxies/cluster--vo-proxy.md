# ClusterVOProxy - Référence Technique

## Description

Proxy facilitant la création d'instances de `ClusterVO` en normalisant automatiquement toutes les valeurs booléennes (PHP et chaînes 'true'/'false') vers les valeurs 'yes'/'no'.

## Hiérarchie

```
ClusterVOProxy
```

## Rôle principal

Agit comme un wrapper de création pour `ClusterVO` qui applique une normalisation récursive des booléens. Il permet de créer des clusters sans se soucier de la conversion des valeurs booléennes, évitant ainsi les exceptions de `ClusterVO` qui n'accepte pas les booléens PHP ni les chaînes 'true'/'false'.

---

## API

### `make(array $data): ClusterVO`

Crée un `ClusterVO` en convertissant récursivement toutes les valeurs booléennes en 'yes'/'no'.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données à normaliser et encapsuler |

**Retourne :** `ClusterVO` - Instance de ClusterVO avec les booléens normalisés

**Exceptions :** `InvalidArgumentException` - Si les données sont vides ou contiennent des types non supportés

**Exemple :**
```php
$cluster = ClusterVOProxy::make([
    'active' => true,
    'verified' => false,
    'name' => 'John Doe',
]);

$cluster->get('active'); // 'yes'
$cluster->get('verified'); // 'no'
$cluster->get('name'); // 'John Doe'
```

---

## Cas d'utilisation

### Cas 1 : Création avec booléens PHP

```php
use AndyDefer\LaravelCluster\Proxies\ClusterVOProxy;

$cluster = ClusterVOProxy::make([
    'is_active' => true,
    'is_deleted' => false,
    'is_verified' => true,
    'user' => [
        'active' => true,
        'suspended' => false,
    ],
]);

$cluster->get('is_active'); // 'yes'
$cluster->get('is_deleted'); // 'no'
$cluster->get('user.active'); // 'yes'
$cluster->get('user.suspended'); // 'no'
```

### Cas 2 : Création avec chaînes 'true'/'false'

```php
$cluster = ClusterVOProxy::make([
    'active' => 'true',
    'verified' => 'false',
    'status' => 'active',
]);

$cluster->get('active'); // 'yes'
$cluster->get('verified'); // 'no'
$cluster->get('status'); // 'active' (préservé)
```

### Cas 3 : Nettoyage de données avant indexation

```php
class Doctor extends Model
{
    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVOProxy::make([
            'status' => $this->is_active,
            'verified' => $this->email_verified_at !== null,
            'has_patients' => $this->patients()->exists(),
            'profile' => $this->profile ? [
                'is_verified' => $this->profile->is_verified,
                'is_accepting' => $this->profile->is_accepting_new_patients,
                'years_experience' => $this->profile->years_of_experience,
            ] : null,
            'specialties' => $this->specialties->pluck('name')->toArray(),
        ]);
    }
}
```

### Cas 4 : Structure profondément imbriquée

```php
$cluster = ClusterVOProxy::make([
    'level1' => [
        'level2' => [
            'level3' => [
                'active' => true,
                'items' => [
                    ['enabled' => true],
                    ['enabled' => false],
                    ['flag' => 'true'],
                ],
            ],
        ],
    ],
]);

// Tous les booléens sont normalisés récursivement
$cluster->get('level1.level2.level3.active'); // 'yes'
$items = json_decode($cluster->get('level1.level2.level3.items'), true);
// $items[0]['enabled'] = 'yes'
// $items[1]['enabled'] = 'no'
// $items[2]['flag'] = 'yes'
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Données vides | `InvalidArgumentException` | `Cluster cannot be empty` |
| Clé non string (top-level) | `InvalidArgumentException` | `Top-level cluster keys must be strings. Got {type}` |
| Objet non stdClass | `InvalidArgumentException` | `Cluster values must be string, int, float, array or null. Got object for key "{key}"` |

---

## Intégration

- **`ClusterVO`** : Le proxy crée directement une instance de ClusterVO
- **`ClusterVOCollection`** : Peut être utilisé avec les clusters créés via le proxy
- **`ClusterCast`** : Peut être utilisé comme alternative dans les modèles Eloquent

---

## Performance

- **Normalisation :** O(n) où n est le nombre de nœuds dans la structure
- **Récursion :** Supporte les structures profondément imbriquées
- **Mémoire :** Crée une copie normalisée des données (double mémoire temporaire)
- **Aucun cache :** La normalisation est effectuée à chaque appel

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Proxies\ClusterVOProxy;

// ==================== CRÉATION SIMPLE ====================

$cluster = ClusterVOProxy::make([
    'id' => 1,
    'name' => 'John Doe',
    'status' => 'active',
    'verified' => true,
    'deleted' => false,
    'preferences' => [
        'notifications' => 'true',
        'dark_mode' => 'false',
    ],
]);

echo $cluster->get('verified'); // 'yes'
echo $cluster->get('deleted'); // 'no'
echo $cluster->get('preferences.notifications'); // 'yes'
echo $cluster->get('preferences.dark_mode'); // 'no'

// ==================== CRÉATION DEPUIS UNE SOURCE EXTERNE ====================

class UserService
{
    public function createUserCluster(array $userData): ClusterVO
    {
        return ClusterVOProxy::make([
            'id' => $userData['id'],
            'name' => $userData['name'],
            'email' => $userData['email'],
            'active' => $userData['is_active'],
            'verified' => $userData['email_verified_at'] !== null,
            'roles' => $userData['roles'],
            'metadata' => $userData['metadata'] ?? null,
        ]);
    }
}

// ==================== UTILISATION AVEC ARRAYACCESS ====================

$cluster = ClusterVOProxy::make([
    'active' => true,
    'verified' => false,
]);

echo $cluster['active']; // 'yes'
echo $cluster['verified']; // 'no'

if (isset($cluster['active'])) {
    // La clé existe
}

// ==================== UTILISATION AVEC COLLECTION ====================

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;

$collection = new ClusterVOCollection();
$collection->add(ClusterVOProxy::make([
    'name' => 'John',
    'active' => true,
    'verified' => false,
]));

$filtered = $collection->whereTrue('active'); // John
```

---

## Voir aussi

- `ClusterVO` - Value Object principal
- `ClusterVOCollection` - Collection de clusters
- `ClusterCast` - Cast Eloquent pour les clusters
- `BooleanNormalizer` - Normalisateur récursif de booléens