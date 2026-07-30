# Lexer - Technical Reference

## Description

Le lexer transforme une chaîne de requête brute en un flux de tokens utilisable par le parseur. Il gère les identifiants, les opérateurs, les parenthèses, les crochets, les chaînes entre guillemets et les motifs LIKE.

## Hiérarchie

```
LexerInterface
    └── Lexer
```

## Rôle principal

Analyse une chaîne de requête et la découpe en tokens selon une grammaire définie. Il identifie :

- **Identifiants** : Noms de clés, chemins, noms de fonctions
- **Opérateurs** : Comparaison (=, !=, >, <, >=, <=, etc.) et logiques (AND, OR, NOT)
- **Parenthèses** : `(` et `)` pour le regroupement
- **Crochets** : `[` et `]` pour les sous-conditions
- **Chaînes entre guillemets** : Simples, doubles ou backticks
- **Motifs LIKE** : Avec `%` comme caractère générique

---

## API

### `tokenize(string $input): TokenRecordCollection`

Tokenize la chaîne d'entrée en une collection de tokens.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$input` | `string` | La requête à tokenizer |

**Retourne :** `TokenRecordCollection` - La collection de tokens

**Exceptions :** `InvalidArgumentException` - Si un caractère invalide est rencontré

**Exemple :**
```php
$lexer = new Lexer();
$tokens = $lexer->tokenize('status=active & COUNT(addresses) > 2');
// Tokens:
// IDENTIFIER:status, OPERATOR:=, IDENTIFIER:active, OPERATOR:&, 
// IDENTIFIER:COUNT, PAREN:(, IDENTIFIER:addresses, PAREN:), 
// OPERATOR:>, IDENTIFIER:2, END
```

---

## Tokens générés

### Types de tokens

| Type | Description | Exemples |
|------|-------------|----------|
| `IDENTIFIER` | Identifiants, valeurs, chemins | `status`, `active`, `addresses`, `city` |
| `OPERATOR` | Opérateurs de comparaison ou logiques | `=`, `!=`, `>`, `>=`, `AND`, `OR`, `NOT` |
| `PAREN` | Parenthèses | `(`, `)` |
| `SUB_OPEN` | Crochet ouvrant de sous-condition | `[` |
| `SUB_CLOSE` | Crochet fermant de sous-condition | `]` |
| `END` | Fin de l'entrée | - |

---

## Cas d'utilisation

### Cas 1 : Requête simple

```php
<?php

use AndyDefer\LaravelCluster\Lexer;

$lexer = new Lexer();
$tokens = $lexer->tokenize('status=active');

foreach ($tokens as $token) {
    echo $token->type . ': ' . $token->value . "\n";
}
// IDENTIFIER: status
// OPERATOR: =
// IDENTIFIER: active
// END:
```

### Cas 2 : Requête avec parenthèses

```php
$tokens = $lexer->tokenize('(status=active & role=admin) | lang_fr');

// Tokens:
// PAREN: (
// IDENTIFIER: status
// OPERATOR: =
// IDENTIFIER: active
// OPERATOR: &
// IDENTIFIER: role
// OPERATOR: =
// IDENTIFIER: admin
// PAREN: )
// OPERATOR: |
// IDENTIFIER: lang_fr
// END
```

### Cas 3 : Requête avec sous-condition

```php
$tokens = $lexer->tokenize('addresses[city=Kinshasa]');

// Tokens:
// IDENTIFIER: addresses
// SUB_OPEN: [
// IDENTIFIER: city
// OPERATOR: =
// IDENTIFIER: Kinshasa
// SUB_CLOSE: ]
// END
```

### Cas 4 : Requête avec fonction SQL

```php
$tokens = $lexer->tokenize('COUNT(addresses) > 2');

// Tokens:
// IDENTIFIER: COUNT
// PAREN: (
// IDENTIFIER: addresses
// PAREN: )
// OPERATOR: >
// IDENTIFIER: 2
// END
```

### Cas 5 : Requête avec LIKE

```php
$tokens = $lexer->tokenize('name=~%john%');

// Tokens:
// IDENTIFIER: name
// OPERATOR: =~
// IDENTIFIER: %john%
// END
```

### Cas 6 : Requête avec guillemets

```php
$tokens = $lexer->tokenize('addresses[city="Kinshasa"]');

// Tokens:
// IDENTIFIER: addresses
// SUB_OPEN: [
// IDENTIFIER: city
// OPERATOR: =
// IDENTIFIER: Kinshasa
// SUB_CLOSE: ]
// END
```

### Cas 7 : Opérateurs spéciaux

```php
// EXISTS (*)
$tokens = $lexer->tokenize('*name');
// OPERATOR: *, IDENTIFIER: name, END

// NOT_EXISTS (#)
$tokens = $lexer->tokenize('#profile');
// OPERATOR: #, IDENTIFIER: profile, END

// NOT
$tokens = $lexer->tokenize('!lang_fr');
// OPERATOR: NOT, IDENTIFIER: lang_fr, END
```

---

## États internes

Le lexer maintient plusieurs états pendant le tokenization :

| État | Description |
|------|-------------|
| `isLikeValueMode` | Actif après un opérateur LIKE (`=~` ou `!~`), permet les `%` dans les identifiants |
| `inSubBracket` | Actif à l'intérieur des crochets `[...]` |
| `inQuotes` | Actif à l'intérieur des guillemets `"..."`, `'...'` ou `` `...` `` |
| `quoteChar` | Le caractère de guillemet utilisé (", ' ou `) |

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Caractère invalide | `InvalidArgumentException` | `Invalid character "{char}" at position {pos}` |
| Guillemet non fermé | Token fermé automatiquement à la fin | - |

---

## Performance

- **Complexité :** O(n) où n est la longueur de la chaîne d'entrée
- **Mémoire :** Stocke tous les tokens en mémoire
- **Optimisation :** Utilise des tableaux pour les opérateurs triés par longueur décroissante

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

$queries = [
    'status=active',
    'status=active & role=admin',
    '(status=active | status=pending) & role=admin',
    'addresses[city=Kinshasa]',
    'COUNT(addresses) > 2',
    'name=~%john%',
    'addresses[city="Kinshasa"]',
    '*name',
    '#profile',
    '!lang_fr',
];

foreach ($queries as $query) {
    echo "Query: $query\n";
    $tokens = $lexer->tokenize($query);
    
    foreach ($tokens as $token) {
        $type = $token->type->name;
        $value = $token->value ?: '(empty)';
        $pos = $token->position;
        echo "  [$type] '$value' @ $pos\n";
    }
    echo "\n";
}

// Filtrage des tokens
$tokens = $lexer->tokenize('status=active & role=admin');

// Récupérer tous les identifiants
$identifiers = $tokens->identifiers();
foreach ($identifiers as $token) {
    echo "Identifier: {$token->value}\n";
}
// Identifier: status, active, role, admin

// Récupérer tous les opérateurs
$operators = $tokens->operators();
foreach ($operators as $token) {
    echo "Operator: {$token->value}\n";
}
// Operator: =, &, =
```

---

## Voir aussi

- `TokenRecordCollection` - Collection de tokens avec méthodes de filtrage
- `Parser` - Parseur qui consomme les tokens
- `OperatorToken` - Énumération des opérateurs
- `TokenType` - Énumération des types de tokens