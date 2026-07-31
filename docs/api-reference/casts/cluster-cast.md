# ClusterCast - Technical Reference

## Description

Le `ClusterCast` est un cast Eloquent qui permet de convertir automatiquement les données JSON d'une colonne en objet `ClusterVO` et vice-versa. Il simplifie l'utilisation des clusters dans les modèles Laravel.

## Hiérarchie

```
Illuminate\Contracts\Database\Eloquent\CastsAttributes
    └── ClusterCast
```

## Rôle principal

Assure la conversion bidirectionnelle entre une colonne JSON en base de données et un objet `ClusterVO`. Il permet :

- **Lors de la lecture** : Transforme le JSON en `ClusterVO`
- **Lors de l'écriture** : Transforme le `ClusterVO` ou un tableau en JSON
- **Validation** : Utilise le constructeur de `ClusterVO` pour valider les données

---

## API

### `get(Model $model, string $key, mixed $value, array $attributes): ?ClusterVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model` | Le modèle Eloquent concerné |
| `$key` | `string` | Le nom de la colonne |
| `$value` | `mixed` | La valeur brute depuis la base de données |
| `$attributes` | `array<string, mixed>` | Tous les attributs du modèle |

**Retourne :** `ClusterVO|null` - L'objet ClusterVO ou `null` si la valeur est vide

**Exemple :**
```php
$user = User::find(1);
$cluster = $user->metadata; // ClusterVO automatiquement casté
$status = $cluster->get('status'); // 'active'
```

---

### `set(Model $model, string $key, mixed $value, array $attributes): ?string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model` | Le modèle Eloquent concerné |
| `$key` | `string` | Le nom de la colonne |
| `$value` | `mixed` | La valeur à stocker |
| `$attributes` | `array<string, mixed>` | Tous les attributs du modèle |

**Retourne :** `string|null` - La valeur JSON à stocker en base, ou `null`

**Exemple :**
```php
$user = new User;
$user->metadata = ['status' => 'active', 'role' => 'admin'];
$user->save(); // Stocké en JSON
```

---

## Cas d'utilisation

### Cas 1 : Utilisation dans un modèle Eloquent

```php
<?php

namespace App\Models;

use AndyDefer\LaravelCluster\Casts\ClusterCast;
use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $casts = [
        'metadata' => ClusterCast::class,
    ];

    protected $fillable = ['name', 'email', 'metadata'];
}
```

### Cas 2 : Création d'un modèle avec ClusterVO

```php
use App\Models\User;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

// Avec un tableau
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'metadata' => [
        'status' => 'active',
        'role' => 'admin',
        'preferences' => [
            'theme' => 'dark',
            'language' => 'fr',
        ],
    ],
]);

// Avec un ClusterVO
$cluster = new ClusterVO([
    'status' => 'active',
    'role' => 'admin',
    'preferences' => [
        'theme' => 'dark',
        'language' => 'fr',
    ],
]);

$user = User::create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'metadata' => $cluster,
]);
```

### Cas 3 : Lecture et manipulation

```php
$user = User::find(1);

// Accès direct (ArrayAccess)
$status = $user->metadata['status']; // 'active'

// Accès via get()
$role = $user->metadata->get('role'); // 'admin'

// Accès via méthode
$theme = $user->metadata->get('preferences.theme'); // 'dark'

// Vérification d'existence
if ($user->metadata->has('preferences.language')) {
    $language = $user->metadata->get('preferences.language');
}
```

### Cas 4 : Mise à jour des données

```php
$user = User::find(1);

// Mise à jour via tableau
$user->metadata = [
    'status' => 'inactive',
    'role' => 'doctor',
];
$user->save();

// Mise à jour via ClusterVO
$cluster = $user->metadata;
$newCluster = new ClusterVO(array_merge($cluster->toArray(), [
    'status' => 'active',
    'age' => 30,
]));
$user->metadata = $newCluster;
$user->save();

// Mise à jour d'une valeur spécifique
$data = $user->metadata->toArray();
$data['status'] = 'pending';
$user->metadata = $data;
$user->save();
```

### Cas 5 : Utilisation avec `whereCluster`

```php
// Les clusters castés peuvent être utilisés avec whereCluster
$activeUsers = User::whereCluster('metadata', 'status=active')->get();

// Avec conditions multiples
$adminUsers = User::whereCluster('metadata', 'status=active & role=admin')->get();

// Avec fonctions SQL
$usersWithManyAddresses = User::whereCluster('metadata', 'COUNT(addresses) > 2')->get();
```

---

## Gestion des erreurs

| Situation | Comportement | Description |
|-----------|--------------|-------------|
| Valeur `null` en base | Retourne `null` | La colonne peut être NULL |
| JSON invalide en lecture | Retourne `null` | Le JSON est ignoré silencieusement |
| Tableau vide | Retourne `null` | Un tableau vide est considéré comme `null` |
| Valeur non array/non string | Retourne `null` | Le cast ignore les valeurs invalides |
| Données invalides en écriture | Exception via `ClusterVO` | Le constructeur de ClusterVO valide les données |

---

## Intégration

Le `ClusterCast` s'intègre avec :

- **`ClusterVO`** : Le Value Object qui représente les données du cluster
- **Eloquent Models** : Via les `$casts` du modèle
- **`whereCluster`** : La macro Eloquent qui filtre sur les colonnes castées
- **`ClusterService`** : Le service principal du package

---

## Performance

- **Complexité :** O(n) où n est la taille du JSON
- **Cache :** Aucun cache, le cast est exécuté à chaque lecture/écriture
- **Recommandation :** Utiliser `select()` pour éviter de charger de grandes colonnes inutilement

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

| Version Laravel | Support |
|-----------------|---------|
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |
| Laravel 12.x | ✅ Complet |
| Laravel 13.x | ✅ Complet |
| Laravel 14.x | ✅ Complet |
| Laravel 15.x | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use App\Models\User;
use AndyDefer\LaravelCluster\Casts\ClusterCast;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Model;

// 1. Définition du modèle
final class User extends Model
{
    protected $casts = [
        'metadata' => ClusterCast::class,
    ];

    protected $fillable = ['name', 'email', 'metadata'];
}

// 2. Création
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'metadata' => [
        'status' => 'active',
        'role' => 'admin',
        'age' => 30,
        'preferences' => [
            'theme' => 'dark',
            'notifications' => true,
        ],
        'addresses' => [
            ['city' => 'Kinshasa', 'country' => 'RDC'],
            ['city' => 'Paris', 'country' => 'France'],
        ],
    ],
]);

// 3. Lecture
$freshUser = User::find($user->id);

// Le metadata est automatiquement un ClusterVO
$cluster = $freshUser->metadata;

echo "Status: " . $cluster->get('status') . "\n"; // active
echo "Role: " . $cluster->get('role') . "\n"; // admin
echo "Theme: " . $cluster->get('preferences.theme') . "\n"; // dark

// Accès via ArrayAccess
echo "Age: " . $cluster['age'] . "\n"; // 30

// 4. Vérification
if ($cluster->has('preferences.notifications')) {
    echo "Notifications: " . $cluster->get('preferences.notifications') . "\n";
}

// 5. Filtrage Eloquent avec oùCluster
$admins = User::whereCluster('metadata', 'role=admin')->get();
echo "Admin users: " . $admins->count() . "\n";

$activeAdmins = User::whereCluster('metadata', 'status=active & role=admin')->get();
echo "Active admins: " . $activeAdmins->count() . "\n";

// 6. Filtrage avec fonction SQL
$usersWithMultipleAddresses = User::whereCluster('metadata', 'COUNT(addresses) > 1')->get();
echo "Users with > 1 address: " . $usersWithMultipleAddresses->count() . "\n";

// 7. Mise à jour
$user->metadata = [
    'status' => 'inactive',
    'role' => 'doctor',
    'age' => 35,
];
$user->save();

// 8. Mise à jour partielle via ClusterVO
$current = $user->metadata->toArray();
$current['status'] = 'active';
$user->metadata = $current;
$user->save();

// 9. Création avec ClusterVO
$cluster = new ClusterVO([
    'status' => 'pending',
    'role' => 'guest',
    'age' => 20,
]);

$newUser = User::create([
    'name' => 'Alice Wonder',
    'email' => 'alice@example.com',
    'metadata' => $cluster,
]);

// 10. Utilisation de toArray pour export
$exportData = $user->metadata->toArray();
print_r($exportData);
```

---

## Voir aussi

- `ClusterVO` - Value Object des clusters
- `ClusterService` - Service principal du package
- `ClusterMacroRegistrar` - Enregistreur des macros
- `ClusterQuery` - Moteur de requêtes