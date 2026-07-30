# AggregateEvaluatorService - Technical Reference

## Description

Service d'évaluation des expressions de fonctions d'agrégation sur des tableaux de données. Il parse et évalue des expressions complexes contenant des fonctions comme COUNT, SUM, AVG, etc. Il supporte les opérateurs logiques (&, |) et gère à la fois les fonctions booléennes et numériques.

## Hiérarchie

```
AggregateEvaluatorService
    └── Utilise AggregateFunctionRegistry
    └── Utilise AggregateExpressionParser
```

## Rôle principal

Moteur d'évaluation des expressions d'agrégation. Il assure :

- **Parsing** : Analyse des expressions en structure de données
- **Évaluation** : Exécution des fonctions sur les données
- **Logique booléenne** : Combinaison des résultats avec AND/OR
- **Fonctions directes** : Exécution sans parsing
- **Validation** : Vérification de la syntaxe des expressions

---

## API

### `__construct(?AggregateFunctionRegistry $registry = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$registry` | `AggregateFunctionRegistry|null` | Registre des fonctions (créé par défaut) |

**Exemple :**
```php
$service = new AggregateEvaluatorService();
// Utilise le registre par défaut

$customRegistry = new AggregateFunctionRegistry();
$service = new AggregateEvaluatorService($customRegistry);
```

---

### `evaluate(array $data, string $expression): bool`

Évalue une expression contre les données fournies.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données à évaluer |
| `$expression` | `string` | L'expression à évaluer |

**Retourne :** `bool` - `true` si l'expression est vraie

**Exemple :**
```php
$data = ['addresses' => ['a', 'b', 'c']];
$result = $service->evaluate($data, '{COUNT(addresses) > 2}');
// true
```

---

### `evaluateDirect(array $data, string $functionName, array $args = []): mixed`

Exécute une fonction directement sans parsing d'expression.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Les données à évaluer |
| `$functionName` | `string` | Le nom de la fonction |
| `$args` | `array<int, string>` | Les arguments de la fonction |

**Retourne :** `mixed` - Le résultat de la fonction

**Exceptions :** `InvalidArgumentException` - Si la fonction n'est pas enregistrée

**Exemple :**
```php
$data = ['addresses' => ['a', 'b', 'c']];
$result = $service->evaluateDirect($data, 'COUNT', ['addresses']);
// 3
```

---

### `validate(string $expression): bool`

Valide la syntaxe d'une expression.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$expression` | `string` | L'expression à valider |

**Retourne :** `bool` - `true` si l'expression est syntaxiquement valide

**Exemple :**
```php
$valid = $service->validate('{COUNT(addresses) > 2}'); // true
$valid = $service->validate('{INVALID(addresses) > 2}'); // false
```

---

### `getRegistry(): AggregateFunctionRegistry`

Retourne le registre des fonctions.

**Retourne :** `AggregateFunctionRegistry` - Le registre

---

### `getParser(): AggregateExpressionParser`

Retourne le parseur d'expressions.

**Retourne :** `AggregateExpressionParser` - Le parseur

---

## Cas d'utilisation

### Cas 1 : Évaluation simple

```php
<?php

use AndyDefer\LaravelCluster\Services\AggregateEvaluatorService;

$service = new AggregateEvaluatorService();

$data = [
    'name' => 'John',
    'addresses' => ['a', 'b', 'c'],
    'scores' => [80, 90, 85],
    'prices' => [100, 200, 300],
];

// COUNT
$result = $service->evaluate($data, '{COUNT(addresses) > 2}'); // true

// SUM
$result = $service->evaluate($data, '{SUM(prices) > 500}'); // true

// AVG
$result = $service->evaluate($data, '{AVG(scores) >= 85}'); // true

// MIN
$result = $service->evaluate($data, '{MIN(scores) > 75}'); // true

// MAX
$result = $service->evaluate($data, '{MAX(scores) < 95}'); // true
```

### Cas 2 : Fonctions booléennes

```php
// EXISTS
$result = $service->evaluate($data, '{EXISTS(addresses)}'); // true

// HAS
$result = $service->evaluate($data, '{HAS(tags, "php")}'); // true

// ALL
$result = $service->evaluate($data, '{ALL(addresses, country, "RDC")}'); // true

// IS_EMPTY
$result = $service->evaluate($data, '{IS_EMPTY(cart)}'); // false
```

### Cas 3 : Expressions complexes avec AND/OR

```php
// AND
$result = $service->evaluate(
    $data,
    '{COUNT(addresses) > 2} & {AVG(scores) >= 85}'
);
// true

// OR
$result = $service->evaluate(
    $data,
    '{COUNT(addresses) > 2} | {SUM(prices) > 500}'
);
// true

// Mixte
$result = $service->evaluate(
    $data,
    '{COUNT(addresses) > 2} & {AVG(scores) >= 85} | {SUM(prices) > 500}'
);
// true
```

### Cas 4 : Évaluation directe

```php
$result = $service->evaluateDirect($data, 'COUNT', ['addresses']); // 3
$result = $service->evaluateDirect($data, 'SUM', ['prices']); // 600.0
$result = $service->evaluateDirect($data, 'AVG', ['scores']); // 85.0
```

### Cas 5 : Validation

```php
$valid = $service->validate('{COUNT(addresses) > 2}'); // true
$valid = $service->validate('{COUNT(addresses > 2}'); // false
$valid = $service->validate('{INVALID(addresses) > 2}'); // false
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fonction non enregistrée | `InvalidArgumentException` | `Function "{name}" not registered` |
| Arguments invalides | `InvalidArgumentException` | Message personnalisé selon la fonction |
| Expression mal formée | Retourne `false` | - |

---

## Performance

- **Parsing :** Les expressions sont mises en cache par `AggregateExpressionParser`
- **Évaluation :** O(n) où n est le nombre de fonctions dans l'expression
- **Validation :** O(n) où n est la longueur de l'expression

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

use AndyDefer\LaravelCluster\Services\AggregateEvaluatorService;

// ==================== INSTANCIATION ====================

$service = new AggregateEvaluatorService();

// ==================== DONNÉES DE TEST ====================

$data = [
    'name' => 'John Doe',
    'status' => 'active',
    'role' => 'admin',
    'age' => 30,
    'addresses' => ['Kinshasa', 'Paris', 'London'],
    'scores' => [80, 90, 85],
    'prices' => [100, 200, 300],
    'tags' => ['php', 'js', 'docker'],
    'addresses_detail' => [
        ['city' => 'Kinshasa', 'country' => 'RDC'],
        ['city' => 'Paris', 'country' => 'France'],
        ['city' => 'London', 'country' => 'UK'],
    ],
    'cart' => ['item1', 'item2'],
];

// ==================== FONCTIONS SIMPLES ====================

echo "COUNT: " . ($service->evaluate($data, '{COUNT(addresses) > 2}') ? 'true' : 'false') . "\n";
// true

echo "SUM: " . ($service->evaluate($data, '{SUM(prices) > 500}') ? 'true' : 'false') . "\n";
// true

echo "AVG: " . ($service->evaluate($data, '{AVG(scores) >= 85}') ? 'true' : 'false') . "\n";
// true

echo "MIN: " . ($service->evaluate($data, '{MIN(scores) > 75}') ? 'true' : 'false') . "\n";
// true

echo "MAX: " . ($service->evaluate($data, '{MAX(scores) < 95}') ? 'true' : 'false') . "\n";
// true

// ==================== FONCTIONS BOOLÉENNES ====================

echo "EXISTS: " . ($service->evaluate($data, '{EXISTS(addresses)}') ? 'true' : 'false') . "\n";
// true

echo "HAS: " . ($service->evaluate($data, '{HAS(tags, "php")}') ? 'true' : 'false') . "\n";
// true

echo "ALL: " . ($service->evaluate($data, '{ALL(addresses_detail, country, "RDC")}') ? 'true' : 'false') . "\n";
// false (tous les pays ne sont pas RDC)

echo "IS_EMPTY: " . ($service->evaluate($data, '{IS_EMPTY(cart)}') ? 'true' : 'false') . "\n";
// false

// ==================== EXPRESSIONS COMPLEXES ====================

echo "AND: " . ($service->evaluate(
    $data,
    '{COUNT(addresses) > 2} & {AVG(scores) >= 85}'
) ? 'true' : 'false') . "\n";
// true

echo "OR: " . ($service->evaluate(
    $data,
    '{COUNT(addresses) > 2} | {SUM(prices) > 1000}'
) ? 'true' : 'false') . "\n";
// true (COUNT > 2 est true)

echo "Mixte: " . ($service->evaluate(
    $data,
    '{COUNT(addresses) > 2} & {AVG(scores) >= 85} | {SUM(prices) > 1000}'
) ? 'true' : 'false') . "\n";
// true

// ==================== ÉVALUATION DIRECTE ====================

echo "Direct COUNT: " . $service->evaluateDirect($data, 'COUNT', ['addresses']) . "\n";
// 3

echo "Direct SUM: " . $service->evaluateDirect($data, 'SUM', ['prices']) . "\n";
// 600.0

echo "Direct AVG: " . $service->evaluateDirect($data, 'AVG', ['scores']) . "\n";
// 85.0

// ==================== VALIDATION ====================

echo "Valid: " . ($service->validate('{COUNT(addresses) > 2}') ? 'true' : 'false') . "\n";
// true

echo "Invalid: " . ($service->validate('{COUNT(addresses > 2}') ? 'true' : 'false') . "\n";
// false

// ==================== ACCÈS AUX SERVICES ====================

$registry = $service->getRegistry();
$parser = $service->getParser();

echo "Registry functions: " . implode(', ', $registry->getNames()) . "\n";
// COUNT, SUM, AVG, MIN, MAX, LENGTH, EXISTS, HAS, ALL, IS_EMPTY
```

---

## Voir aussi

- `AggregateFunctionRegistry` - Registre des fonctions d'agrégation
- `AggregateExpressionParser` - Analyseur d'expressions
- `AggregateOperator` - Énumération des opérateurs
- `AbstractAggregateFunction` - Classe abstraite des fonctions