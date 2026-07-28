# TokenRecordCollection - Référence Technique

## Description

Collection typée spécialisée pour la gestion d'objets `TokenRecord` avec des capacités de filtrage basées sur le type, la valeur, la position et les catégories sémantiques des tokens (opérateurs, identifiants, parenthèses, etc.).

## Hiérarchie

```
AbstractTypedCollection
    └── TokenRecordCollection
```

**Interfaces :** Aucune (hérite de `AbstractTypedCollection`)

## Rôle principal

Cette collection sert de conteneur intelligent pour des objets `TokenRecord` représentant des tokens d'expression. Elle permet de :

- Filtrer les tokens par type (`TokenType`)
- Filtrer par valeur (exacte ou multiple)
- Isoler des catégories spécifiques (opérateurs, identifiants, parenthèses)
- Distinguer les opérateurs de comparaison des opérateurs logiques
- Accéder aux tokens par leur position dans l'expression
- Extraire les valeurs des tokens sous forme de collection de chaînes

Cette collection est particulièrement utile dans les analyseurs syntaxiques, les moteurs de requêtes et les systèmes de parsing d'expressions.

---

## API / Méthodes publiques

### Filtres par catégorie

#### `operators(): self`

Filtre la collection pour n'inclure que les tokens de type opérateur.

**Retourne :** `self` - Nouvelle collection contenant uniquement les tokens opérateurs

**Exemple :**
```php
$operators = $tokens->operators();
// $operators contient uniquement les tokens de type Operator
```

---

#### `identifiers(): self`

Filtre la collection pour n'inclure que les tokens de type identifiant.

**Retourne :** `self` - Nouvelle collection contenant uniquement les tokens identifiants

**Exemple :**
```php
$identifiers = $tokens->identifiers();
// $identifiers contient uniquement les tokens de type Identifier
```

---

#### `parens(): self`

Filtre la collection pour n'inclure que les tokens de type parenthèse.

**Retourne :** `self` - Nouvelle collection contenant uniquement les tokens parenthèses

**Exemple :**
```php
$parens = $tokens->parens();
// $parens contient uniquement les tokens '(' et ')'
```

---

### Filtres par type

#### `ofType(TokenType $type): self`

Filtre la collection pour n'inclure que les tokens d'un type spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `TokenType` | Type de token à filtrer |

**Retourne :** `self` - Nouvelle collection contenant uniquement les tokens du type spécifié

**Exemple :**
```php
$identifiers = $tokens->ofType(TokenType::Identifier);
$strings = $tokens->ofType(TokenType::String);
$numbers = $tokens->ofType(TokenType::Number);
```

---

### Filtres par valeur

#### `withValue(string $value): self`

Filtre la collection pour n'inclure que les tokens ayant une valeur spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Valeur exacte à rechercher |

**Retourne :** `self` - Nouvelle collection contenant uniquement les tokens avec la valeur spécifiée

**Exemple :**
```php
$equalsTokens = $tokens->withValue('=');
$andTokens = $tokens->withValue('AND');
```

---

#### `withValues(array $values): self`

Filtre la collection pour n'inclure que les tokens dont la valeur est dans le tableau donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$values` | `array<string>` | Tableau des valeurs acceptées |

**Retourne :** `self` - Nouvelle collection contenant uniquement les tokens avec les valeurs correspondantes

**Exemple :**
```php
$comparisonOps = $tokens->withValues(['=', '!=', '<', '>', '<=', '>=']);
```

---

### Filtres d'exclusion

#### `withoutEnd(): self`

Filtre la collection pour exclure les tokens de fin d'expression.

**Retourne :** `self` - Nouvelle collection sans les tokens de fin

**Exemple :**
```php
$tokensWithoutEnd = $tokens->withoutEnd();
// Exclut les tokens de type TokenType::End
```

---

### Filtres par catégorie d'opérateurs

#### `comparisonOperators(): self`

Filtre la collection pour n'inclure que les tokens d'opérateurs de comparaison.

Inclut les opérateurs comme `=`, `!=`, `<`, `>`, `<=`, `>=`, `LIKE`, etc.

**Retourne :** `self` - Nouvelle collection contenant uniquement les tokens d'opérateurs de comparaison

**Exemple :**
```php
$comparisonOps = $tokens->comparisonOperators();
// Retourne : =, !=, <, >, <=, >=, LIKE
```

---

#### `logicalOperators(): self`

Filtre la collection pour n'inclure que les tokens d'opérateurs logiques.

Inclut les opérateurs comme `AND`, `OR`, `NOT`.

**Retourne :** `self` - Nouvelle collection contenant uniquement les tokens d'opérateurs logiques

**Exemple :**
```php
$logicalOps = $tokens->logicalOperators();
// Retourne : AND, OR, NOT
```

---

#### `pureComparisonOperators(): self`

Filtre la collection pour n'inclure que les opérateurs de comparaison purs.

Retourne uniquement les opérateurs de comparaison (`=`, `!=`, `<`, `>`, `<=`, `>=`, `LIKE`) en excluant explicitement les opérateurs logiques (`AND`, `OR`, `NOT`).

**Retourne :** `self` - Nouvelle collection contenant uniquement les tokens d'opérateurs de comparaison purs

**Exemple :**
```php
$pureComparisonOps = $tokens->pureComparisonOperators();
// Retourne : =, !=, <, >, <=, >=, LIKE
// Exclut : AND, OR, NOT
```

---

#### `pureLogicalOperators(): self`

Filtre la collection pour n'inclure que les opérateurs logiques purs.

Retourne uniquement les opérateurs logiques (`AND`, `OR`, `NOT`).

**Retourne :** `self` - Nouvelle collection contenant uniquement les tokens d'opérateurs logiques purs

**Exemple :**
```php
$pureLogicalOps = $tokens->pureLogicalOperators();
// Retourne : AND, OR, NOT
```

---

### Accès par position

#### `atPosition(int $position): ?TokenRecord`

Récupère le token à une position spécifique dans l'expression.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$position` | `int` | Index de position à rechercher |

**Retourne :** `TokenRecord|null` - Token à la position, ou `null` si non trouvé

**Exemple :**
```php
$firstToken = $tokens->atPosition(0);
$secondToken = $tokens->atPosition(1);
```

---

#### `fromPosition(int $position): self`

Crée une nouvelle collection contenant les tokens à partir d'une position spécifique (inclusive).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$position` | `int` | Position de départ (incluse) |

**Retourne :** `self` - Nouvelle collection avec les tokens à partir de la position

**Exemple :**
```php
$remainingTokens = $tokens->fromPosition(3);
// Contient les tokens aux positions 3, 4, 5, ...
```

---

### Extraction de valeurs

#### `values(): StringTypedCollection`

Extrait et retourne les valeurs de tous les tokens sous forme de collection de chaînes.

**Retourne :** `StringTypedCollection` - Collection contenant uniquement les valeurs des tokens

**Exemple :**
```php
$values = $tokens->values();
// Retourne : ['id', '=', '123', 'AND', 'status', '=', 'active']

foreach ($values as $value) {
    echo $value . PHP_EOL;
}
```

---

## Cas d'utilisation

### Cas 1 : Analyse d'une expression de filtrage

Extraire les composants d'une expression de filtrage complexe.

```php
<?php

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;
use AndyDefer\LaravelCluster\Records\TokenRecord;
use AndyDefer\LaravelCluster\Enums\TokenType;

// Supposons que nous ayons une collection de tokens
$tokens = new TokenRecordCollection();
// Ajout des tokens : "id", "=", "123", "AND", "status", "=", "active"

// Extraire les identifiants
$identifiers = $tokens->identifiers();
// Résultat : "id", "status"

// Extraire les opérateurs de comparaison
$comparisonOps = $tokens->comparisonOperators();
// Résultat : "=", "="

// Extraire les valeurs (opérandes)
$values = $tokens->values();
// Résultat : ["id", "=", "123", "AND", "status", "=", "active"]
```

---

### Cas 2 : Validation d'une expression SQL WHERE

Valider la structure d'une clause WHERE en vérifiant les opérateurs.

```php
<?php

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;

function validateWhereClause(TokenRecordCollection $tokens): bool
{
    // Vérifier qu'il y a au moins un opérateur de comparaison
    $comparisonCount = $tokens->pureComparisonOperators()->count();
    if ($comparisonCount === 0) {
        return false;
    }

    // Vérifier que les opérateurs logiques sont bien placés
    $logicalOps = $tokens->pureLogicalOperators();
    foreach ($logicalOps as $op) {
        // Vérifier que chaque opérateur logique a des opérandes des deux côtés
        // (logique métier spécifique)
    }

    return true;
}
```

---

### Cas 3 : Extraction des conditions d'une requête

Extraire les paires champ-opérateur-valeur d'une expression de requête.

```php
<?php

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;

function extractConditions(TokenRecordCollection $tokens): array
{
    $conditions = [];
    $currentCondition = [];

    foreach ($tokens as $token) {
        if ($token->type->isOperator() && 
            in_array($token->value, ['AND', 'OR', 'NOT'], true)) {
            if (!empty($currentCondition)) {
                $conditions[] = $currentCondition;
                $currentCondition = [];
            }
            $conditions[] = ['operator' => $token->value];
            continue;
        }
        
        $currentCondition[] = $token;
    }

    if (!empty($currentCondition)) {
        $conditions[] = $currentCondition;
    }

    return $conditions;
}

// Utilisation
$tokens = new TokenRecordCollection();
// ... ajout des tokens : id = 123 AND status = 'active'

$conditions = extractConditions($tokens);
// Résultat : [
//     [TokenRecord(id), TokenRecord(=), TokenRecord(123)],
//     ['operator' => 'AND'],
//     [TokenRecord(status), TokenRecord(=), TokenRecord(active)]
// ]
```

---

### Cas 4 : Suggestion d'auto-complétion

Fournir des suggestions d'auto-complétion basées sur le contexte des tokens.

```php
<?php

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;
use AndyDefer\LaravelCluster\Enums\TokenType;

function suggestNextToken(TokenRecordCollection $tokens, string $prefix): array
{
    // Filtrer les identifiants qui commencent par le préfixe
    $suggestions = $tokens
        ->identifiers()
        ->filter(function ($token) use ($prefix) {
            return str_starts_with($token->value, $prefix);
        });

    // Récupérer les valeurs uniques
    return array_unique($suggestions->values()->toArray());
}

// Utilisation
$suggestions = suggestNextToken($tokens, 'us');
// Retourne : ['user', 'username', 'user_id']
```

---

## Gestion des erreurs

La classe `TokenRecordCollection` ne lève pas directement d'exceptions. Elle délègue la validation des types à la classe parente `AbstractTypedCollection` qui peut lever des exceptions si un objet de type incorrect est ajouté.

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un objet non `TokenRecord` | `InvalidArgumentException` | Dépend de l'implémentation parente |
| Position de token non trouvée | `null` retourné, pas d'exception | N/A |
| Filtre sans résultat | Collection vide retournée, pas d'exception | N/A |

---

## Intégration

`TokenRecordCollection` s'intègre avec :

- **`TokenRecord`** : L'objet manipulé par la collection
- **`TokenType`** : Enumération des types de tokens
- **`ComparisonOperator`** : Enumération des opérateurs de comparaison
- **`LogicalOperator`** : Enumération des opérateurs logiques
- **`StringTypedCollection`** : Utilisée pour le retour de la méthode `values()`

Cette collection est typiquement utilisée par :

- **Analyseurs syntaxiques** : Pour traiter les expressions
- **Moteurs de requêtes** : Pour parser les conditions WHERE
- **Systèmes d'auto-complétion** : Pour suggérer les tokens suivants
- **Validateurs d'expressions** : Pour vérifier la syntaxe

---

## Performance

### Complexité algorithmique

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `operators()`, `identifiers()` | O(n) | Parcourt tous les tokens |
| `ofType()` | O(n) | Parcourt tous les tokens |
| `withValue()` | O(n) | Parcourt tous les tokens |
| `withValues()` | O(n * m) | n = tokens, m = valeurs à vérifier |
| `atPosition()` | O(n) | Parcourt jusqu'à la position trouvée |
| `fromPosition()` | O(n) | Parcourt tous les tokens |
| `values()` | O(n) | Extrait les valeurs de tous les tokens |
| `pureComparisonOperators()` | O(n) | Parcourt tous les tokens avec vérification des enums |
| `pureLogicalOperators()` | O(n) | Parcourt tous les tokens avec vérification des enums |

### Optimisations

- Les filtres créent une **nouvelle collection** à chaque appel, préservant l'originale
- Utilisation de méthodes d'énumération (`isOperator()`, `isIdentifier()`) pour des vérifications sémantiques rapides
- Comparaisons strictes (`===`) pour les opérations d'égalité
- Les collections sont typées, évitant les vérifications de type runtime

### Considérations mémoire

- Chaque opération de filtrage crée une nouvelle collection
- `values()` crée une nouvelle `StringTypedCollection` avec les valeurs
- Pour de très grandes collections, les multiples filtrages peuvent consommer de la mémoire
- Les méthodes avec `in_array()` sur les valeurs d'énumération sont optimisées

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (nécessite PHP 8.0+) |

**Dépendances :**
- `AbstractTypedCollection` - Collection typée de base
- `StringTypedCollection` - Collection typée pour les chaînes
- `TokenRecord` - Objet token
- `TokenType`, `ComparisonOperator`, `LogicalOperator` - Enums de types et opérateurs

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;
use AndyDefer\LaravelCluster\Records\TokenRecord;
use AndyDefer\LaravelCluster\Enums\TokenType;

// Création de la collection
$tokens = new TokenRecordCollection();

// Ajout de tokens représentant une clause WHERE
$tokens->add(new TokenRecord(
    position: 0,
    type: TokenType::Identifier,
    value: 'user_id'
));

$tokens->add(new TokenRecord(
    position: 1,
    type: TokenType::Operator,
    value: '='
));

$tokens->add(new TokenRecord(
    position: 2,
    type: TokenType::Number,
    value: '123'
));

$tokens->add(new TokenRecord(
    position: 3,
    type: TokenType::Operator,
    value: 'AND'
));

$tokens->add(new TokenRecord(
    position: 4,
    type: TokenType::Identifier,
    value: 'status'
));

$tokens->add(new TokenRecord(
    position: 5,
    type: TokenType::Operator,
    value: '='
));

$tokens->add(new TokenRecord(
    position: 6,
    type: TokenType::String,
    value: 'active'
));

$tokens->add(new TokenRecord(
    position: 7,
    type: TokenType::End,
    value: ''
));

// Filtrage : extraire les identifiants
$identifiers = $tokens->identifiers();
echo "Identifiants trouvés : " . count($identifiers) . PHP_EOL;
// Résultat : 2 (user_id, status)

// Filtrage : extraire les opérateurs de comparaison
$comparisonOps = $tokens->comparisonOperators();
echo "Opérateurs de comparaison : " . count($comparisonOps) . PHP_EOL;
// Résultat : 2 (=, =)

// Filtrage : extraire les opérateurs logiques
$logicalOps = $tokens->logicalOperators();
echo "Opérateurs logiques : " . count($logicalOps) . PHP_EOL;
// Résultat : 1 (AND)

// Extraire toutes les valeurs
$values = $tokens->values();
echo "Valeurs : " . implode(' ', $values->toArray()) . PHP_EOL;
// Résultat : "user_id = 123 AND status = active"

// Accès par position
$firstToken = $tokens->atPosition(0);
echo "Premier token : " . $firstToken->value . PHP_EOL;
// Résultat : "user_id"

// Tokens à partir de la position 3
$remaining = $tokens->fromPosition(3);
echo "Tokens restants : " . count($remaining) . PHP_EOL;
// Résultat : 5 (AND, status, =, active, end)

// Utilisation avancée : opérateurs purs
$pureComparison = $tokens->pureComparisonOperators();
echo "Opérateurs de comparaison purs : " . count($pureComparison) . PHP_EOL;
// Résultat : 2 (=, =)

$pureLogical = $tokens->pureLogicalOperators();
echo "Opérateurs logiques purs : " . count($pureLogical) . PHP_EOL;
// Résultat : 1 (AND)

// Chaînage de filtres : opérateurs de comparaison qui ne sont pas logiques
$nonLogicalComparison = $tokens
    ->comparisonOperators()
    ->filter(function ($token) use ($logicalOps) {
        return !in_array($token->value, ['AND', 'OR', 'NOT'], true);
    });

echo "Opérateurs non-logiques : " . count($nonLogicalComparison) . PHP_EOL;
// Résultat : 2 (=, =)
```

---

## Voir aussi

- `TokenRecord` - Structure de données représentant un token
- `TokenType` - Énumération des types de tokens
- `ComparisonOperator` - Énumération des opérateurs de comparaison
- `LogicalOperator` - Énumération des opérateurs logiques
- `StringTypedCollection` - Collection typée pour les chaînes
- `AbstractTypedCollection` - Classe parente des collections typées