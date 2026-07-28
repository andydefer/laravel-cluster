# Lexer - Référence Technique

## Description

Analyseur lexical (tokenizer) qui transforme une expression textuelle en une séquence de tokens. Il identifie les parenthèses, les opérateurs, les identifiants et les valeurs, en gérant spécifiquement les opérateurs LIKE avec leurs motifs.

## Hiérarchie

```
Lexer
```

**Interfaces :** `LexerInterface`

## Rôle principal

`Lexer` est le premier maillon de la chaîne de traitement des requêtes. Il :

- **Tokenize** : Transforme la chaîne d'entrée en une collection de tokens
- **Identifie les catégories** : Parenthèses, opérateurs, identifiants
- **Gère les opérateurs multi-caractères** : `>=`, `<=`, `!=`, `LIKE`, etc.
- **Support spécifique pour LIKE** : Permet l'utilisation de `%` comme caractère joker
- **Gère les espaces** : Ignore les espaces entre les tokens

Le lexer est conçu pour être rapide et précis, avec une gestion appropriée des cas particuliers comme les opérateurs LIKE.

---

## API / Méthodes publiques

### `tokenize(string $input): TokenRecordCollection`

Analyse la chaîne d'entrée et produit une collection de tokens.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$input` | `string` | Expression à tokeniser |

**Retourne :** `TokenRecordCollection` - Collection de tokens

**Exceptions :** `InvalidArgumentException` - Si un caractère invalide est rencontré

**Exemple :**
```php
$lexer = new Lexer();
$tokens = $lexer->tokenize('age > 18 AND status = "active"');
// Résultat : [Identifier(age), Operator(>), Identifier(18), Operator(AND), Identifier(status), Operator(=), Identifier(active), END]
```

---

### Méthodes privées

| Méthode | Rôle |
|---------|------|
| `isWhitespace()` | Vérifie si un caractère est un espace |
| `isParen()` | Vérifie si un caractère est une parenthèse |
| `isIdentifierStart()` | Vérifie si un caractère peut commencer un identifiant |
| `matchOperatorToken()` | Identifie les opérateurs multi-caractères |
| `readIdentifierOrLikeValue()` | Lit un identifiant ou une valeur LIKE avec `%` |
| `readIdentifier()` | Lit un identifiant simple |

---

## Cas d'utilisation

### Cas 1 : Tokenisation d'une expression simple

Analyser une condition de base.

```php
<?php

use AndyDefer\LaravelCluster\Lexer;

$lexer = new Lexer();
$tokens = $lexer->tokenize('age >= 18');

foreach ($tokens as $token) {
    echo $token->type->name . ': ' . $token->value . PHP_EOL;
}
// Résultat :
// IDENTIFIER: age
// OPERATOR: >=
// IDENTIFIER: 18
// END:
```

---

### Cas 2 : Tokenisation d'expression avec parenthèses

Analyser une expression avec regroupement.

```php
<?php

$tokens = $lexer->tokenize('(status = "active" OR role = "admin")');

// Résultat :
// PAREN: (
// IDENTIFIER: status
// OPERATOR: =
// IDENTIFIER: active
// OPERATOR: OR
// IDENTIFIER: role
// OPERATOR: =
// IDENTIFIER: admin
// PAREN: )
// END:
```

---

### Cas 3 : Gestion des opérateurs LIKE

Tokeniser des expressions avec LIKE et motifs.

```php
<?php

use AndyDefer\LaravelCluster\Lexer;

$lexer = new Lexer();

// LIKE avec motif simple
$tokens = $lexer->tokenize('name LIKE "John%"');
// Résultat : IDENTIFIER(name), OPERATOR(LIKE), IDENTIFIER(John%), END

// NOT LIKE avec motif
$tokens = $lexer->tokenize('email NOT LIKE "%@gmail.com"');
// Résultat : IDENTIFIER(email), OPERATOR(NOT_LIKE), IDENTIFIER(%@gmail.com), END

// LIKE avec % en début
$tokens = $lexer->tokenize('title LIKE "%PHP%"');
// Résultat : IDENTIFIER(title), OPERATOR(LIKE), IDENTIFIER(%PHP%), END
```

---

### Cas 4 : Gestion des opérateurs multi-caractères

Identifier correctement les opérateurs composés.

```php
<?php

$lexer = new Lexer();

// Opérateurs composés
$tokens = $lexer->tokenize('age >= 18 AND score <= 100');

// Les opérateurs >= et <= sont reconnus comme un seul token
foreach ($tokens as $token) {
    if ($token->type->isOperator()) {
        echo "Opérateur: " . $token->value . PHP_EOL;
    }
}
// Résultat :
// Opérateur: >=
// Opérateur: AND
// Opérateur: <=
```

---

### Cas 5 : Gestion des espaces et caractères valides

Tolérance aux espaces et identification des identifiants.

```php
<?php

$lexer = new Lexer();

// Espaces multiples
$tokens = $lexer->tokenize('age    >    18');
// Les espaces sont ignorés, tokens corrects

// Identifiants avec underscores et tirets
$tokens = $lexer->tokenize('user_id = "john-doe"');
// Résultat : IDENTIFIER(user_id), OPERATOR(=), IDENTIFIER(john-doe)
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Caractère invalide | `InvalidArgumentException` | `Invalid character "{char}" at position {position}` |

### Caractères invalides

Les caractères suivants ne sont pas autorisés et déclenchent une exception :

- Caractères spéciaux non reconnus : `$`, `#`, `@`, `&`, etc.
- Symboles non pris en charge comme opérateurs
- Guillemets non échappés dans les identifiants

### Exemple d'erreur

```php
try {
    $lexer->tokenize('age $ 18'); // $ n'est pas valide
} catch (InvalidArgumentException $e) {
    echo $e->getMessage();
    // "Invalid character "$" at position 4"
}
```

---

## Intégration

`Lexer` s'intègre avec :

- **`LexerInterface`** : Interface du lexer
- **`TokenRecord`** : Objet token
- **`TokenRecordCollection`** : Collection de tokens
- **`TokenType`** : Énumération des types de tokens
- **`OperatorToken`** : Énumération des opérateurs
- **`Parser`** : Utilise les tokens produits pour construire l'AST

### Dans la chaîne de traitement

```
Expression textuelle
    ↓
[Lexer] → Tokens
    ↓
[Parser] → AST
    ↓
[ClusterQuery] → Évaluation / SQL / Eloquent
```

---

## Performance

### Complexité algorithmique

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `tokenize()` | O(n) | n = longueur de la chaîne |
| `matchOperatorToken()` | O(k * m) | k = nombre d'opérateurs, m = longueur max |

### Optimisations

- Parcours unique de la chaîne
- Pas d'expressions régulières (évite les surcoûts)
- Tri des opérateurs par longueur pour la correspondance
- Pas d'allocations mémoire superflues

### Considérations

- La recherche des opérateurs utilise `usort()` à chaque appel → peut être optimisée avec un pré-tri
- Les opérateurs sont recherchés en priorité par longueur décroissante pour capturer `>=` avant `>`

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (nécessite PHP 8.0+) |

**Dépendances :**
- `LexerInterface` - Interface
- `TokenRecord` - Record de token
- `TokenRecordCollection` - Collection
- `TokenType` - Enum des types
- `OperatorToken` - Enum des opérateurs

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Lexer;
use AndyDefer\LaravelCluster\Enums\TokenType;

// 1. Instanciation
$lexer = new Lexer();

// 2. Tokenisation d'une requête complexe
$query = '(age >= 18 AND status = "active") OR (role = "admin" AND verified = "true")';
$tokens = $lexer->tokenize($query);

// 3. Affichage détaillé des tokens
echo "=== TOKENS ===\n";
foreach ($tokens as $token) {
    $type = $token->type->name;
    $value = $token->value;
    $pos = $token->position;
    echo "[{$type}] '{$value}' at position {$pos}\n";
}

// 4. Comptage par type
$types = [];
foreach ($tokens as $token) {
    $typeName = $token->type->name;
    $types[$typeName] = ($types[$typeName] ?? 0) + 1;
}

echo "\n=== STATISTIQUES ===\n";
foreach ($types as $type => $count) {
    echo "{$type}: {$count}\n";
}

// 5. Extraction des opérateurs
echo "\n=== OPÉRATEURS ===\n";
foreach ($tokens as $token) {
    if ($token->type->isOperator()) {
        echo "- {$token->value}\n";
    }
}

// 6. Extraction des identifiants
echo "\n=== IDENTIFIANTS ===\n";
foreach ($tokens as $token) {
    if ($token->type->isIdentifier()) {
        echo "- {$token->value}\n";
    }
}

// 7. Gestion des opérateurs LIKE
echo "\n=== TOKENISATION LIKE ===\n";
$likeQuery = 'name LIKE "John%" AND email NOT LIKE "%@gmail.com"';
$likeTokens = $lexer->tokenize($likeQuery);

foreach ($likeTokens as $token) {
    if ($token->type->isOperator() || $token->type->isIdentifier()) {
        echo $token->type->name . ": " . $token->value . PHP_EOL;
    }
}

// 8. Tests des cas limites
echo "\n=== CAS LIMITES ===\n";

// Requête vide
$emptyTokens = $lexer->tokenize('');
echo "Requête vide: " . $emptyTokens->count() . " tokens\n";
// Résultat: 1 token (END)

// Identifiants avec tirets
$tokens = $lexer->tokenize('user-id = "john-doe"');
echo "Identifiant avec tiret: " . $tokens->first()->value . "\n";
// Résultat: user-id

// 9. Gestion des erreurs
echo "\n=== GESTION DES ERREURS ===\n";
try {
    $lexer->tokenize('invalid @ character');
} catch (InvalidArgumentException $e) {
    echo "Erreur capturée: " . $e->getMessage() . PHP_EOL;
}

// 10. Utilisation avec Parser
echo "\n=== UTILISATION AVEC PARSER ===\n";
use AndyDefer\LaravelCluster\Parser;

$parser = new Parser();
$ast = $parser->parse('age >= 18 AND status = "active"');
echo "AST parsé avec succès\n";

// 11. Vérification du type des tokens
echo "\n=== VÉRIFICATION DES TYPES ===\n";
$testTokens = $lexer->tokenize('age = 18');

foreach ($testTokens as $token) {
    $isOperator = $token->type->isOperator() ? '✅' : '❌';
    $isIdentifier = $token->type->isIdentifier() ? '✅' : '❌';
    $isParen = $token->type->isParen() ? '✅' : '❌';
    $isEnd = $token->type->isEnd() ? '✅' : '❌';

    echo "Token '{$token->value}':\n";
    echo "  Opérateur: {$isOperator}\n";
    echo "  Identifiant: {$isIdentifier}\n";
    echo "  Parenthèse: {$isParen}\n";
    echo "  Fin: {$isEnd}\n";
}
```

---

## Voir aussi

- `Parser` - Parseur utilisant les tokens
- `TokenRecord` - Structure des tokens
- `TokenRecordCollection` - Collection de tokens
- `TokenType` - Énumération des types
- `OperatorToken` - Énumération des opérateurs
- `LexerInterface` - Interface du lexer
- `ClusterQuery` - Moteur de requêtes