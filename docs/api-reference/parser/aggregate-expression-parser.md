# AggregateExpressionParser - Technical Reference

## Description

Analyse les expressions de fonctions d'agrégation avec support des fonctions imbriquées et des combinaisons logiques. Il traite des expressions comme `{COUNT(addresses) > 2}`, `{COUNT({LENGTH(name) > 5}) > 2}` et `{COUNT({LENGTH(name) > 5} & {SUM(prices) > 100}) > 2}`.

## Hiérarchie

```
AggregateExpressionParser
    └── Utilise AggregateFunctionRegistry
```

## Rôle principal

Transforme les expressions d'agrégation en une structure de données utilisable par le moteur d'évaluation. Il supporte :

- **Fonctions simples** : `{COUNT(addresses) > 2}`
- **Fonctions imbriquées** : `{COUNT({LENGTH(name) > 5}) > 2}`
- **Combinaisons logiques** : `{COUNT({LENGTH(name) > 5} & {SUM(prices) > 100}) > 2}`
- **Arguments variés** : Variables (`$var`), tableaux (`[1, 2, 3]`), chaînes entre guillemets, valeurs booléennes 'yes'/'no'

---

## API

### `__construct(AggregateFunctionRegistry $registry)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$registry` | `AggregateFunctionRegistry` | Le registre des fonctions d'agrégation |

**Exemple :**
```php
$registry = new AggregateFunctionRegistry();
$parser = new AggregateExpressionParser($registry);
```

---

### `parse(string $expression): ?array`

Analyse une expression d'agrégation et retourne sa représentation structurée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$expression` | `string` | L'expression à analyser |

**Retourne :** `array{functionName: string, args: array, operator: AggregateOperator|null, value: mixed}|null` - La structure parsée ou `null` en cas d'échec

**Exemple :**
```php
$result = $parser->parse('{COUNT(addresses) > 2}');
// [
//     'functionName' => 'COUNT',
//     'args' => ['addresses'],
//     'operator' => AggregateOperator::GREATER_THAN,
//     'value' => 2,
// ]
```

---

### `split(string $expression): array`

Découpe une expression composée en parties individuelles avec leurs opérateurs.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$expression` | `string` | L'expression composée |

**Retourne :** `array<int, array{expression: string, operator: string}>` - Les parties avec leurs opérateurs

**Exemple :**
```php
$parts = $parser->split('{COUNT(addresses) > 2} & {AVG(scores) >= 85}');
// [
//     ['expression' => '{COUNT(addresses) > 2}', 'operator' => '&'],
//     ['expression' => '{AVG(scores) >= 85}', 'operator' => '&'],
// ]
```

---

## Structure de retour

### Fonction simple

```php
[
    'functionName' => 'COUNT',
    'args' => ['addresses'],
    'operator' => AggregateOperator::GREATER_THAN,
    'value' => 2,
]
```

### Fonction booléenne (sans opérateur)

```php
[
    'functionName' => 'EXISTS',
    'args' => ['addresses'],
    'operator' => null,
    'value' => null,
]
```

### Fonction imbriquée

```php
[
    'functionName' => 'COUNT',
    'args' => [
        [
            'functionName' => 'LENGTH',
            'args' => ['name'],
            'operator' => AggregateOperator::GREATER_THAN,
            'value' => 5,
        ],
    ],
    'operator' => AggregateOperator::GREATER_THAN,
    'value' => 2,
]
```

### Expression complexe

```php
[
    'functionName' => 'COUNT',
    'args' => [
        [
            'type' => 'complex_expression',
            'parts' => [
                [
                    'expression' => '{LENGTH(name) > 5}',
                    'operator' => '&',
                    'parsed' => [/* ... */],
                ],
                [
                    'expression' => '{SUM(prices) > 100}',
                    'operator' => '&',
                    'parsed' => [/* ... */],
                ],
            ],
            'original' => '{LENGTH(name) > 5} & {SUM(prices) > 100}',
        ],
    ],
    'operator' => AggregateOperator::GREATER_THAN,
    'value' => 2,
]
```

---

## Types d'arguments supportés

| Type | Format | Exemple |
|------|--------|---------|
| **Chaîne** | `'valeur'` ou `"valeur"` | `'Kinshasa'` |
| **Variable** | `$nom` | `$prices` |
| **Tableau** | `[1, 2, 3]` | `[1, 2, 3]` |
| **Tableau imbriqué** | `[1, [2, 3], 4]` | `[1, [2, 3], 4]` |
| **Fonction imbriquée** | `{...}` | `{LENGTH(name) > 5}` |
| **Booléen** | `'yes'` ou `'no'` | `'yes'` |
| **Null** | `null` | `null` |
| **Numérique** | `123` ou `45.67` | `42` |

---

## Cas d'utilisation

### Cas 1 : Fonction simple

```php
$result = $parser->parse('{COUNT(addresses) > 2}');
```

### Cas 2 : Fonction booléenne

```php
$result = $parser->parse('{EXISTS(addresses)}');
// operator = null, value = null
```

### Cas 3 : Fonction avec argument variable

```php
$result = $parser->parse('{SUM($prices) > 500}');
// args = [['type' => 'variable', 'value' => 'prices']]
```

### Cas 4 : Fonction avec tableau

```php
$result = $parser->parse('{COUNT([1, 2, 3]) > 2}');
// args = [[1, 2, 3]]
```

### Cas 5 : Fonction avec arguments multiples et valeur 'yes'

```php
$result = $parser->parse('{HAS(addresses, city, "Kinshasa")}');
// args = ['addresses', 'city', 'Kinshasa']

// Avec valeur booléenne 'yes'
$result = $parser->parse('{HAS(settings, notifications, "yes")}');
// args = ['settings', 'notifications', 'yes']
```

### Cas 6 : Fonction imbriquée avec valeurs booléennes

```php
$result = $parser->parse('{COUNT({LENGTH(name) > 5}) > 2}');

// Avec valeur 'yes'
$result = $parser->parse('{COUNT({verified = yes}) > 2}');
```

### Cas 7 : Expression complexe imbriquée avec booléens

```php
$result = $parser->parse('{COUNT({status=active} & {verified=yes}) > 2}');
```

### Cas 8 : Découpage d'expression composée

```php
$parts = $parser->split('{COUNT(addresses) > 2} & {AVG(scores) >= 85} | {SUM(prices) > 500}');
// 3 parties avec opérateurs '&', '|'
```

### Cas 9 : Fonction avec valeur booléenne 'no'

```php
$result = $parser->parse('{COUNT({status=inactive} & {verified=no}) > 2}');
// args = [
//     [
//         'functionName' => 'HAS',
//         'args' => ['status', '=', 'inactive'],
//         'operator' => AggregateOperator::AND,
//         'value' => [
//             'functionName' => 'HAS',
//             'args' => ['verified', '=', 'no'],
//         ],
//     ],
// ]
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Arguments invalides | `InvalidArgumentException` | `Invalid arguments for function "{name}". Required: {min}-{max} args, got {count}` |
| Fonction inconnue | Retourne `null` | - |

---

## Performance

- **Complexité :** O(n) où n est la longueur de l'expression
- **Cache :** Les résultats parsés sont mis en cache par expression (clé MD5)
- **Récursion :** Supporte les fonctions imbriquées jusqu'à la profondeur maximale définie par le registre

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

use AndyDefer\LaravelCluster\Parser\AggregateExpressionParser;
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;
use AndyDefer\LaravelCluster\Enums\AggregateOperator;

// ==================== INSTANCIATION ====================

$registry = new AggregateFunctionRegistry();
$parser = new AggregateExpressionParser($registry);

// ==================== FONCTIONS SIMPLES ====================

$simple = $parser->parse('{COUNT(addresses) > 2}');
print_r($simple);
// [
//     'functionName' => 'COUNT',
//     'args' => ['addresses'],
//     'operator' => AggregateOperator::GREATER_THAN,
//     'value' => 2,
// ]

// ==================== FONCTIONS BOOLÉENNES ====================

$boolean = $parser->parse('{EXISTS(addresses)}');
print_r($boolean);
// [
//     'functionName' => 'EXISTS',
//     'args' => ['addresses'],
//     'operator' => null,
//     'value' => null,
// ]

// ==================== VARIABLES ET TABLEAUX ====================

$variable = $parser->parse('{SUM($prices) > 500}');
// args = [['type' => 'variable', 'value' => 'prices']]

$array = $parser->parse('{COUNT([1, 2, 3]) > 2}');
// args = [[1, 2, 3]]

// ==================== FONCTIONS IMBRIQUÉES ====================

$nested = $parser->parse('{COUNT({LENGTH(name) > 5}) > 2}');
print_r($nested);
// [
//     'functionName' => 'COUNT',
//     'args' => [
//         [
//             'functionName' => 'LENGTH',
//             'args' => ['name'],
//             'operator' => AggregateOperator::GREATER_THAN,
//             'value' => 5,
//         ],
//     ],
//     'operator' => AggregateOperator::GREATER_THAN,
//     'value' => 2,
// ]

// ==================== FONCTIONS AVEC VALEURS BOOLÉENNES ====================

$withBoolean = $parser->parse('{HAS(settings, notifications, "yes")}');
print_r($withBoolean);
// [
//     'functionName' => 'HAS',
//     'args' => ['settings', 'notifications', 'yes'],
//     'operator' => null,
//     'value' => null,
// ]

// ==================== EXPRESSIONS COMPLEXES ====================

$complex = $parser->parse('{COUNT({status=active} & {verified=yes}) > 2}');
// L'argument est une expression complexe avec des valeurs booléennes

// ==================== DÉCOUPAGE ====================

$parts = $parser->split('{COUNT(addresses) > 2} & {AVG(scores) >= 85} | {SUM(prices) > 500}');
foreach ($parts as $part) {
    echo "Expression: {$part['expression']}\n";
    echo "Operator: {$part['operator']}\n\n";
}
// Expression: {COUNT(addresses) > 2}
// Operator: &
//
// Expression: {AVG(scores) >= 85}
// Operator: |
//
// Expression: {SUM(prices) > 500}
// Operator: |
```

---

## Voir aussi

- `AggregateFunctionRegistry` - Registre des fonctions d'agrégation
- `AggregateEvaluatorService` - Service d'évaluation des expressions
- `AggregateOperator` - Énumération des opérateurs d'agrégation
