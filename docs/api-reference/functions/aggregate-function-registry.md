# AggregateFunctionRegistry - Référence Technique

## Description

Registre central qui gère l'ensemble des fonctions d'agrégation disponibles pour les requêtes. Il permet d'enregistrer, rechercher et exécuter des fonctions comme COUNT, SUM, AVG, MIN, MAX, ainsi que des fonctions booléennes comme EXISTS, HAS, ALL, IS_EMPTY et MATCHES.

## Hiérarchie / Implémentations

```
AggregateFunctionRegistry (classe finale)
```

## Rôle principal

L'`AggregateFunctionRegistry` est le point d'entrée pour toutes les fonctions d'agrégation dans Laravel Cluster. Il :

- **Enregistre** les fonctions d'agrégation (COUNT, SUM, AVG, MIN, MAX, LENGTH, EXISTS, HAS, ALL, IS_EMPTY, MATCHES)
- **Valide** les noms de fonctions selon la convention SCREAMING_SNAKE_CASE
- **Exécute** les fonctions sur des données en mémoire
- **Fournit** les métadonnées des fonctions (type de retour, valeur par défaut, etc.)
- **Filtre** les fonctions par type (booléennes vs numériques)

## API / Méthodes publiques

### `register(AggregateFunctionInterface $function): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$function` | `AggregateFunctionInterface` | La fonction à enregistrer |

**Retourne :** `self` - L'instance du registre pour le chaînage

**Exceptions :** 
- `InvalidArgumentException` - Si une fonction du même nom est déjà enregistrée
- `InvalidArgumentException` - Si le nom de la fonction est invalide

**Exemple :**
```php
$registry = new AggregateFunctionRegistry();
$customFunction = new CustomFunction();
$registry->register($customFunction);
```

---

### `has(string $name): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction (insensible à la casse) |

**Retourne :** `bool` - `true` si la fonction est enregistrée

**Exemple :**
```php
$registry = new AggregateFunctionRegistry();
$exists = $registry->has('COUNT'); // true
$exists = $registry->has('UNKNOWN'); // false
```

---

### `get(string $name): ?AggregateFunctionInterface`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction (insensible à la casse) |

**Retourne :** `AggregateFunctionInterface|null` - L'instance de la fonction, ou `null` si non trouvée

**Exemple :**
```php
$registry = new AggregateFunctionRegistry();
$function = $registry->get('COUNT');
// Retourne une instance de CountFunction
```

---

### `execute(string $name, array $data, array $args): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction à exécuter |
| `$data` | `array<string, mixed>` | Les données à traiter |
| `$args` | `array<int, string>` | Les arguments de la fonction |

**Retourne :** `mixed` - Le résultat de l'exécution de la fonction

**Exceptions :** `InvalidArgumentException` - Si la fonction n'est pas enregistrée

**Exemple :**
```php
$registry = new AggregateFunctionRegistry();

// COUNT
$data = ['addresses' => ['a', 'b', 'c']];
$result = $registry->execute('COUNT', $data, ['addresses']); // 3

// SUM
$data = ['prices' => [10, 20, 30]];
$result = $registry->execute('SUM', $data, ['prices']); // 60.0

// HAS
$data = ['tags' => ['php', 'js', 'docker']];
$result = $registry->execute('HAS', $data, ['tags', 'php']); // true
```

---

### `getDefaultValue(string $name): mixed`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom de la fonction |

**Retourne :** `mixed` - La valeur par défaut, ou `0` si la fonction n'est pas trouvée

**Exemple :**
```php
$registry = new AggregateFunctionRegistry();

$default = $registry->getDefaultValue('COUNT'); // 0
$default = $registry->getDefaultValue('EXISTS'); // false
```

---

### `all(): array`

**Retourne :** `array<string, AggregateFunctionInterface>` - Toutes les fonctions enregistrées

**Exemple :**
```php
$registry = new AggregateFunctionRegistry();
$functions = $registry->all();
// ['COUNT' => CountFunction, 'SUM' => SumFunction, ...]
```

---

### `getBooleanFunctions(): array`

**Retourne :** `array<string, AggregateFunctionInterface>` - Les fonctions qui retournent des booléens

**Exemple :**
```php
$registry = new AggregateFunctionRegistry();
$booleanFunctions = $registry->getBooleanFunctions();
// ['EXISTS', 'HAS', 'ALL', 'IS_EMPTY', 'MATCHES']
```

---

### `getNumericFunctions(): array`

**Retourne :** `array<string, AggregateFunctionInterface>` - Les fonctions qui retournent des nombres

**Exemple :**
```php
$registry = new AggregateFunctionRegistry();
$numericFunctions = $registry->getNumericFunctions();
// ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'LENGTH']
```

---

### `getNames(): array`

**Retourne :** `array<string>` - Les noms de toutes les fonctions enregistrées

**Exemple :**
```php
$registry = new AggregateFunctionRegistry();
$names = $registry->getNames();
// ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'LENGTH', 'EXISTS', 'HAS', 'ALL', 'IS_EMPTY', 'MATCHES']
```

## Cas d'utilisation

### Cas 1 : Utiliser les fonctions d'agrégation dans une requête

```php
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$registry = new AggregateFunctionRegistry();
$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John Doe',
    'addresses' => ['a', 'b', 'c'],
    'scores' => [80, 90, 85],
]));

// Utiliser COUNT
$data = $collection->first()->getUnflattened()->toArray();
$count = $registry->execute('COUNT', $data, ['addresses']); // 3

// Utiliser AVG
$avg = $registry->execute('AVG', $data, ['scores']); // 85.0
```

### Cas 2 : Filtrer une collection avec des fonctions booléennes

```php
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$collection = new ClusterVOCollection();
$collection->add(new ClusterVO([
    'name' => 'John',
    'tags' => ['php', 'js', 'docker'],
]));
$collection->add(new ClusterVO([
    'name' => 'Jane',
    'tags' => ['python', 'react'],
]));

// Filtrer les clusters qui contiennent 'php' dans leurs tags
$filtered = $collection->whereAggregate('{HAS(tags, php)}');
// John uniquement
```

### Cas 3 : Ajouter une fonction personnalisée

```php
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;
use AndyDefer\LaravelCluster\Functions\AbstractAggregateFunction;

class DoubleCountFunction extends AbstractAggregateFunction
{
    public function execute(array $data, array $args): int
    {
        $path = $args[0] ?? null;
        $items = $this->resolvePath($data, $path);
        
        if (!is_array($items)) {
            return 0;
        }
        
        return count($items) * 2;
    }
    
    public function getName(): string { return 'DOUBLE_COUNT'; }
    public function getDefaultValue(): mixed { return 0; }
    public function getReturnType(): string { return 'int'; }
    public function returnsBoolean(): bool { return false; }
    public function getMinArgs(): int { return 1; }
    public function getMaxArgs(): int { return 1; }
    public function validateArgs(array $args): bool { return count($args) === 1; }
}

$registry = new AggregateFunctionRegistry();
$registry->register(new DoubleCountFunction());

// Utilisation
$data = ['addresses' => ['a', 'b', 'c']];
$result = $registry->execute('DOUBLE_COUNT', $data, ['addresses']); // 6
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fonction déjà enregistrée | `InvalidArgumentException` | `Function "X" is already registered. Cannot register duplicate.` |
| Nom de fonction invalide | `InvalidArgumentException` | `Invalid function name "X". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.` |
| Fonction non enregistrée | `InvalidArgumentException` | `Function "X" not registered` |
| Arguments invalides | Erreur spécifique à la fonction | Dépend de l'implémentation de la fonction |

## Intégration

L'`AggregateFunctionRegistry` est utilisé par :

- **`AggregateEvaluatorService`** : Pour évaluer les expressions d'agrégation
- **`AggregateExpressionParser`** : Pour parser les expressions d'agrégation
- **`ClusterVOCollection`** : Pour les méthodes `whereAggregate`, `matchesAggregate`, etc.
- **`ClusterQuery`** : Pour le filtrage des collections avec des fonctions d'agrégation

### Cycle de vie d'une fonction

```
1. Fonction enregistrée dans le registre (register)
   ↓
2. Parser détecte la fonction dans l'expression
   ↓
3. Parser valide les arguments via validateArgs()
   ↓
4. Évaluation : execute() pour les clusters en mémoire
   ↓
5. Résultat retourné à l'appelant
```

## Performance

- **Recherche** : O(1) via tableau associatif
- **Enregistrement** : O(1)
- **Exécution** : O(n) où n est la taille des données traitées
- **Mémoire** : Une instance par fonction enregistrée
- **Initialisation** : Les 11 fonctions par défaut sont enregistrées à la construction

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;

$registry = new AggregateFunctionRegistry();

// 1. Vérifier les fonctions disponibles
var_dump($registry->getNames());
// ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'LENGTH', 'EXISTS', 'HAS', 'ALL', 'IS_EMPTY', 'MATCHES']

// 2. Vérifier les fonctions booléennes
var_dump($registry->getBooleanFunctions());
// ['EXISTS', 'HAS', 'ALL', 'IS_EMPTY', 'MATCHES']

// 3. Exécuter COUNT
$data = ['addresses' => ['a', 'b', 'c']];
$result = $registry->execute('COUNT', $data, ['addresses']);
var_dump($result); // 3

// 4. Exécuter HAS
$data = ['tags' => ['php', 'js', 'docker']];
$result = $registry->execute('HAS', $data, ['tags', 'php']);
var_dump($result); // true

// 5. Exécuter ALL
$data = [
    'items' => [
        ['status' => 'active'],
        ['status' => 'active'],
        ['status' => 'active'],
    ],
];
$result = $registry->execute('ALL', $data, ['items', 'status', 'active']);
var_dump($result); // true

// 6. Exécuter MATCHES
$data = ['name' => 'John Doe'];
$result = $registry->execute('MATCHES', $data, ['name', '/^John/']);
var_dump($result); // true

// 7. Exécuter IS_EMPTY
$data = ['empty_array' => []];
$result = $registry->execute('IS_EMPTY', $data, ['empty_array']);
var_dump($result); // true

// 8. Exécuter EXISTS
$data = ['present' => 'value'];
$result = $registry->execute('EXISTS', $data, ['present']);
var_dump($result); // true
```

## Voir aussi

- [`AggregateFunctionInterface`](Contracts/AggregateFunctionInterface.md) - Interface des fonctions d'agrégation
- [`AbstractAggregateFunction`](Functions/AbstractAggregateFunction.md) - Classe abstraite pour les fonctions d'agrégation
- [`AggregateEvaluatorService`](Services/AggregateEvaluatorService.md) - Service d'évaluation des expressions
- [`AggregateExpressionParser`](Parser/AggregateExpressionParser.md) - Parser des expressions d'agrégation
- [`ClusterVOCollection`](Collections/ClusterVOCollection.md) - Collection de clusters avec méthodes d'agrégation
- [`CountFunction`](Functions/CountFunction.md) - Fonction COUNT
- [`SumFunction`](Functions/SumFunction.md) - Fonction SUM
- [`AvgFunction`](Functions/AvgFunction.md) - Fonction AVG
- [`MinFunction`](Functions/MinFunction.md) - Fonction MIN
- [`MaxFunction`](Functions/MaxFunction.md) - Fonction MAX
- [`LengthFunction`](Functions/LengthFunction.md) - Fonction LENGTH
- [`ExistsFunction`](Functions/ExistsFunction.md) - Fonction EXISTS
- [`HasFunction`](Functions/HasFunction.md) - Fonction HAS
- [`AllFunction`](Functions/AllFunction.md) - Fonction ALL
- [`IsEmptyFunction`](Functions/IsEmptyFunction.md) - Fonction IS_EMPTY
- [`MatchesFunction`](Functions/MatchesFunction.md) - Fonction MATCHES