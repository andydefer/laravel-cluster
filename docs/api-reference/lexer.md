# Lexer - Référence Technique

## Description

Analyseur lexical qui transforme une chaîne de requête textuelle en un flux de tokens compréhensibles par le `Parser`.

## Hiérarchie / Implémentations

```
LexerInterface
    └── Lexer
```

## Rôle principal

Le `Lexer` est la première étape du traitement des requêtes dans Laravel Cluster. Il prend une chaîne brute (ex: `"status=active & COUNT(addresses) > 2"`) et la découpe en une collection de tokens (`TokenRecordCollection`) que le `Parser` pourra ensuite organiser en Arbre Syntaxique Abstrait (AST).

### Fonctionnalités clés

- **Identifiants** : Noms de champs, chemins (ex: `status`, `addresses`, `profile.languages`)
- **Opérateurs** : Comparaison (`=`, `!=`, `>`, `<`, `>=`, `<=`, `===`, `!==`, `<=>`), logiques (`&`, `|`), existence (`*`, `#`), LIKE (`=~`, `!~`)
- **Parenthèses** : `(`, `)` pour le regroupement
- **Crochets** : `[`, `]` pour les sous-conditions
- **Chaînes entre guillemets** : Simples (`'`), doubles (`"`), backticks (`` ` ``)
- **Motifs LIKE** : Gestion du caractère `%` pour les recherches partielles

## API / Méthodes publiques

### `tokenize(string $input): TokenRecordCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$input` | `string` | La requête textuelle à tokeniser |

**Retourne :** `TokenRecordCollection` - Collection de tokens représentant la requête

**Exceptions :** `InvalidArgumentException` - Si un caractère invalide est rencontré

**Exemple :**
```php
$lexer = new Lexer();
$tokens = $lexer->tokenize('status=active & COUNT(addresses) > 2');
// TokenRecordCollection contenant :
// [IDENTIFIER:status, OPERATOR:=, IDENTIFIER:active, OPERATOR:&, IDENTIFIER:COUNT, PAREN:(, IDENTIFIER:addresses, PAREN:), OPERATOR:>, IDENTIFIER:2]
```

## Cas d'utilisation

### Cas 1 : Condition simple
```php
$lexer = new Lexer();
$tokens = $lexer->tokenize('status=active');
// [IDENTIFIER:status, OPERATOR:=, IDENTIFIER:active]
```

### Cas 2 : Condition avec espaces
```php
$lexer = new Lexer();
$tokens = $lexer->tokenize('status = active');
// [IDENTIFIER:status, OPERATOR:=, IDENTIFIER:active]
// Les espaces sont ignorés
```

### Cas 3 : Fonction SQL
```php
$lexer = new Lexer();
$tokens = $lexer->tokenize('COUNT(addresses) > 2');
// [IDENTIFIER:COUNT, PAREN:(, IDENTIFIER:addresses, PAREN:), OPERATOR:>, IDENTIFIER:2]
```

### Cas 4 : CONTAINS avec virgule
```php
$lexer = new Lexer();
$tokens = $lexer->tokenize('CONTAINS(languages, fr)');
// [IDENTIFIER:CONTAINS, PAREN:(, IDENTIFIER:languages, OPERATOR:,, IDENTIFIER:fr, PAREN:)]
// La virgule est un token OPERATOR séparé
```

### Cas 5 : Sous-condition
```php
$lexer = new Lexer();
$tokens = $lexer->tokenize('addresses[city=Kinshasa]');
// [IDENTIFIER:addresses, SUB_OPEN:[, IDENTIFIER:city, OPERATOR:=, IDENTIFIER:Kinshasa, SUB_CLOSE:]]
```

### Cas 6 : Chaîne entre guillemets
```php
$lexer = new Lexer();
$tokens = $lexer->tokenize('addresses[city="Kinshasa"]');
// [IDENTIFIER:addresses, SUB_OPEN:[, IDENTIFIER:city, OPERATOR:=, IDENTIFIER:Kinshasa, SUB_CLOSE:]]
// Les guillemets sont traités mais ne font pas partie de la valeur
```

### Cas 7 : Motif LIKE
```php
$lexer = new Lexer();
$tokens = $lexer->tokenize('name=~%john%');
// Le mode LIKE est activé, le % est autorisé
// [IDENTIFIER:name, OPERATOR:=~, IDENTIFIER:%john%]
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Caractère invalide | `InvalidArgumentException` | `Invalid character "X" at position N` |
| Chaîne non fermée | `InvalidArgumentException` | `Invalid character "X" at position N` (levée via le caractère suivant) |

## Intégration

Le `Lexer` est la première étape du pipeline de traitement des requêtes :

```
Requête textuelle
    ↓
Lexer::tokenize() → TokenRecordCollection
    ↓
Parser::parse() → AST (Node)
    ↓
Évaluation ou génération SQL
```

### Types de tokens produits

| TokenType | Description | Exemple |
|-----------|-------------|---------|
| `IDENTIFIER` | Identifiant (champ, chemin, valeur) | `status`, `addresses`, `active` |
| `OPERATOR` | Opérateur | `=`, `&`, `|`, `,` |
| `PAREN` | Parenthèse | `(`, `)` |
| `SUB_OPEN` | Crochet ouvrant | `[` |
| `SUB_CLOSE` | Crochet fermant | `]` |
| `END` | Fin de la chaîne | `''` |

## Performance

- **Complexité** : O(n) où n est la longueur de la chaîne
- **Mémoire** : La collection de tokens est créée en mémoire
- **Optimisation** : La virgule est traitée comme un opérateur séparé, ce qui permet de parser correctement les fonctions avec plusieurs arguments

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Lexer;

$lexer = new Lexer();

// 1. Condition simple
$tokens = $lexer->tokenize('status=active');
foreach ($tokens as $token) {
    echo $token->type->value . ': ' . $token->value . "\n";
}
// IDENTIFIER: status
// OPERATOR: =
// IDENTIFIER: active

// 2. Fonction CONTAINS
$tokens = $lexer->tokenize('CONTAINS(languages, fr)');
foreach ($tokens as $token) {
    echo $token->type->value . ': ' . $token->value . "\n";
}
// IDENTIFIER: CONTAINS
// PAREN: (
// IDENTIFIER: languages
// OPERATOR: ,
// IDENTIFIER: fr
// PAREN: )

// 3. Sous-condition complexe
$tokens = $lexer->tokenize('addresses[(city=Kinshasa | city=Paris)]');
foreach ($tokens as $token) {
    echo $token->type->value . ': ' . $token->value . "\n";
}
// IDENTIFIER: addresses
// SUB_OPEN: [
// PAREN: (
// IDENTIFIER: city
// OPERATOR: =
// IDENTIFIER: Kinshasa
// OPERATOR: |
// IDENTIFIER: city
// OPERATOR: =
// IDENTIFIER: Paris
// PAREN: )
// SUB_CLOSE: ]
```

## Voir aussi

- [`Parser`](Parser.md) - Analyse syntaxique des tokens
- [`TokenRecord`](Records/TokenRecord.md) - Représentation d'un token
- [`TokenRecordCollection`](Collections/TokenRecordCollection.md) - Collection de tokens
- [`OperatorToken`](Enums/OperatorToken.md) - Énumération des opérateurs
- [`TokenType`](Enums/TokenType.md) - Énumération des types de tokens