# TokenRecordCollection - Technical Reference

## Description

Collection typée d'objets TokenRecord avec des capacités de filtrage spécialisées. Elle fournit des méthodes dédiées pour filtrer les tokens par type, valeur ou position, facilitant le travail avec les flux de tokens du lexer.

## Hiérarchie

```
AbstractTypedCollection<TokenRecord>
    └── TokenRecordCollection
```

## Rôle principal

Collection spécialisée pour les tokens générés par le lexer. Elle permet de :

- **Filtrer par type** : opérateurs, identifiants, parenthèses, etc.
- **Filtrer par valeur** : valeur exacte ou multiples valeurs
- **Accéder par position** : recherche par position ou à partir d'une position
- **Exclure les tokens de fin** : pour le parsing
- **Distinguer les opérateurs** : comparaison vs logiques

---

## API

### `__construct()`

Crée une nouvelle collection vide.

**Exemple :**
```php
$collection = new TokenRecordCollection();
```

---

### `operators(): self`

Filtre et retourne uniquement les tokens d'opérateurs.

**Retourne :** `self` - Collection contenant uniquement les opérateurs

**Exemple :**
```php
$operators = $tokens->operators();
```

---

### `identifiers(): self`

Filtre et retourne uniquement les tokens d'identifiants.

**Retourne :** `self` - Collection contenant uniquement les identifiants

**Exemple :**
```php
$identifiers = $tokens->identifiers();
```

---

### `parens(): self`

Filtre et retourne uniquement les tokens de parenthèses.

**Retourne :** `self` - Collection contenant uniquement les parenthèses

**Exemple :**
```php
$parens = $tokens->parens();
```

---

### `ofType(TokenType $type): self`

Filtre les tokens par leur type.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `TokenType` | Le type de token à filtrer |

**Retourne :** `self` - Collection contenant uniquement les tokens du type donné

**Exemple :**
```php
$identifiers = $tokens->ofType(TokenType::IDENTIFIER);
```

---

### `withValue(string $value): self`

Filtre les tokens par leur valeur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | La valeur à rechercher |

**Retourne :** `self` - Collection contenant uniquement les tokens avec la valeur donnée

**Exemple :**
```php
$statusTokens = $tokens->withValue('status');
```

---

### `withValues(array $values): self`

Filtre les tokens par plusieurs valeurs.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$values` | `array<string>` | Les valeurs à rechercher |

**Retourne :** `self` - Collection contenant uniquement les tokens avec les valeurs données

**Exemple :**
```php
$roleTokens = $tokens->withValues(['role', 'status']);
```

---

### `withoutEnd(): self`

Exclut le token de fin de la collection.

**Retourne :** `self` - Collection sans le token END

**Exemple :**
```php
$tokensWithoutEnd = $tokens->withoutEnd();
```

---

### `comparisonOperators(): self`

Filtre et retourne uniquement les tokens d'opérateurs de comparaison.

**Retourne :** `self` - Collection contenant uniquement les opérateurs de comparaison

**Exemple :**
```php
$comparisons = $tokens->comparisonOperators();
```

---

### `logicalOperators(): self`

Filtre et retourne uniquement les tokens d'opérateurs logiques.

**Retourne :** `self` - Collection contenant uniquement les opérateurs logiques

**Exemple :**
```php
$logicals = $tokens->logicalOperators();
```

---

### `atPosition(int $position): ?TokenRecord`

Récupère un token par sa position.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$position` | `int` | La position à rechercher |

**Retourne :** `TokenRecord|null` - Le token à la position donnée, ou null si non trouvé

**Exemple :**
```php
$token = $tokens->atPosition(7);
```

---

### `fromPosition(int $position): self`

Retourne tous les tokens à partir de la position donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$position` | `int` | La position de départ |

**Retourne :** `self` - Collection contenant les tokens à partir de la position

**Exemple :**
```php
$remaining = $tokens->fromPosition(35);
```

---

### `values(): StringTypedCollection`

Retourne toutes les valeurs des tokens sous forme de collection de chaînes.

**Retourne :** `StringTypedCollection` - Les valeurs des tokens

**Exemple :**
```php
$values = $tokens->values();
// ['status', '=', 'active', 'AND', ...]
```

---

### `pureComparisonOperators(): self`

Filtre et retourne uniquement les tokens d'opérateurs de comparaison purs. Exclut les opérateurs logiques (AND, OR) du résultat.

**Retourne :** `self` - Collection contenant uniquement les opérateurs de comparaison purs

**Exemple :**
```php
$pureComparisons = $tokens->pureComparisonOperators();
```

---

### `pureLogicalOperators(): self`

Filtre et retourne uniquement les tokens d'opérateurs logiques purs.

**Retourne :** `self` - Collection contenant uniquement les opérateurs logiques

**Exemple :**
```php
$pureLogicals = $tokens->pureLogicalOperators();
```

---

### `subOpens(): self`

Filtre et retourne uniquement les tokens de crochets ouvrants de sous-conditions (`[`).

**Retourne :** `self` - Collection contenant uniquement les tokens SUB_OPEN

**Exemple :**
```php
$subOpens = $tokens->subOpens();
```

---

### `subCloses(): self`

Filtre et retourne uniquement les tokens de crochets fermants de sous-conditions (`]`).

**Retourne :** `self` - Collection contenant uniquement les tokens SUB_CLOSE

**Exemple :**
```php
$subCloses = $tokens->subCloses();
```

---

## Cas d'utilisation

### Cas 1 : Filtrage des opérateurs

```php
$tokens = $lexer->tokenize('status=active & role=admin');

$operators = $tokens->operators();
foreach ($operators as $op) {
    echo $op->value . "\n"; // =, &, =
}
```

### Cas 2 : Récupération des identifiants

```php
$identifiers = $tokens->identifiers();
foreach ($identifiers as $id) {
    echo $id->value . "\n"; // status, active, role, admin
}
```

### Cas 3 : Distinction des opérateurs

```php
// Opérateurs de comparaison uniquement
$comparisons = $tokens->comparisonOperators();
// =, =

// Opérateurs logiques uniquement
$logicals = $tokens->logicalOperators();
// AND

// Opérateurs de comparaison purs (sans AND/OR)
$pure = $tokens->pureComparisonOperators();
// =, =
```

### Cas 4 : Recherche par position

```php
$token = $tokens->atPosition(7);
if ($token) {
    echo "Token at position 7: {$token->value}\n";
}

// Tokens à partir de la position 16
$remaining = $tokens->fromPosition(16);
```

### Cas 5 : Crochets de sous-conditions

```php
$tokens = $lexer->tokenize('addresses[city=Kinshasa]');

$subOpens = $tokens->subOpens();
// Token avec valeur '['

$subCloses = $tokens->subCloses();
// Token avec valeur ']'
```

### Cas 6 : Exclusion du token END

```php
$tokens = $lexer->tokenize('status=active');

$withoutEnd = $tokens->withoutEnd();
// 3 tokens (sans END)

$all = $tokens;
// 4 tokens (avec END)
```

---

## Performance

- **Complexité :** O(n) pour chaque filtre où n est le nombre de tokens
- **Mémoire :** Chaque filtre crée une nouvelle collection
- **Optimisation :** Utilisation de `array_filter` avec des appels de méthode

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

use AndyDefer\LaravelCluster\Lexer;
use AndyDefer\LaravelCluster\Enums\TokenType;

$lexer = new Lexer();
$tokens = $lexer->tokenize('status=active & role=admin & addresses[city=Kinshasa]');

// ==================== FILTRAGE PAR TYPE ====================

$operators = $tokens->operators();
echo "Operators (" . $operators->count() . "):\n";
foreach ($operators as $op) {
    echo "  {$op->value}\n";
}
// Operators (5):
//   =
//   &
//   =
//   &
//   =

$identifiers = $tokens->identifiers();
echo "Identifiers (" . $identifiers->count() . "):\n";
foreach ($identifiers as $id) {
    echo "  {$id->value}\n";
}
// Identifiers (8):
//   status
//   active
//   role
//   admin
//   addresses
//   city
//   Kinshasa
//   (identifier dans la sous-condition)

// ==================== FILTRAGE PAR VALEUR ====================

$equalTokens = $tokens->withValue('=');
echo "Equals tokens (" . $equalTokens->count() . "):\n";
foreach ($equalTokens as $token) {
    echo "  Position: {$token->position}\n";
}
// Equals tokens (2):
//   Position: 6
//   Position: 24

// ==================== DISTINCTION DES OPÉRATEURS ====================

$comparisons = $tokens->comparisonOperators();
echo "Comparison operators (" . $comparisons->count() . "):\n";
foreach ($comparisons as $op) {
    echo "  {$op->value}\n";
}
// Comparison operators (2):
//   =
//   =

$logicals = $tokens->logicalOperators();
echo "Logical operators (" . $logicals->count() . "):\n";
foreach ($logicals as $op) {
    echo "  {$op->value}\n";
}
// Logical operators (2):
//   AND
//   AND

// ==================== CROCHETS DE SOUS-CONDITIONS ====================

$subOpens = $tokens->subOpens();
echo "Sub-opens (" . $subOpens->count() . "):\n";
foreach ($subOpens as $token) {
    echo "  Value: {$token->value}, Position: {$token->position}\n";
}
// Sub-opens (1):
//   Value: [, Position: 33

$subCloses = $tokens->subCloses();
echo "Sub-closes (" . $subCloses->count() . "):\n";
foreach ($subCloses as $token) {
    echo "  Value: {$token->value}, Position: {$token->position}\n";
}
// Sub-closes (1):
//   Value: ], Position: 48

// ==================== POSITION ====================

$token = $tokens->atPosition(6);
if ($token) {
    echo "Token at position 6: {$token->value} ({$token->type->name})\n";
}
// Token at position 6: = (OPERATOR)

$fromPosition = $tokens->fromPosition(24);
echo "Tokens from position 24:\n";
foreach ($fromPosition as $token) {
    echo "  {$token->value} ({$token->type->name}) @ {$token->position}\n";
}
// = (OPERATOR) @ 24
// role (IDENTIFIER) @ 26
// AND (OPERATOR) @ 32
// addresses (IDENTIFIER) @ 35
// [ (SUB_OPEN) @ 44
// city (IDENTIFIER) @ 45
// = (OPERATOR) @ 50
// Kinshasa (IDENTIFIER) @ 52
// ] (SUB_CLOSE) @ 60
// END @ 60

// ==================== VALEURS ====================

$values = $tokens->values();
echo "All values:\n";
foreach ($values as $value) {
    echo "  '$value'\n";
}
// 'status', '=', 'active', 'AND', 'role', '=', 'admin', 'AND', 'addresses', '[', 'city', '=', 'Kinshasa', ']', ''

// ==================== EXCLUSION DU END ====================

$withoutEnd = $tokens->withoutEnd();
echo "Without END: " . $withoutEnd->count() . " tokens\n";
// Without END: 15 tokens
```

---

## Voir aussi

- `TokenRecord` - Enregistrement d'un token
- `TokenType` - Énumération des types de tokens
- `Lexer` - Générateur de tokens
- `AbstractTypedCollection` - Collection typée parente