# Parser - Technical Reference

## Description

Le parser transforme un flux de tokens généré par le lexer en un Arbre Syntaxique Abstrait (AST) représentant la structure de la requête. Il gère les opérateurs logiques, les comparaisons, les fonctions SQL, les sous-conditions et les parenthèses.

## Hiérarchie

```
ParserInterface
    └── Parser
```

## Rôle principal

Analyse la structure grammaticale de la requête et construit une arborescence de nœuds (Node) qui représente la sémantique de la requête. Cette structure peut ensuite être évaluée en mémoire ou convertie en SQL.

### Types de nœuds produits

| Nœud | Description |
|------|-------------|
| `ConditionNode` | Condition simple (ex: `status=active`) |
| `GroupNode` | Groupe de conditions avec opérateur logique (AND/OR) |
| `SubConditionNode` | Sous-condition sur un tableau (ex: `addresses[city=Kinshasa]`) |
| `FunctionNode` | Fonction SQL (ex: `COUNT(addresses) > 2`) |

---

## API

### `parse(string $query): Node`

Parse une chaîne de requête en un Arbre Syntaxique Abstrait.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | La requête à parser |

**Retourne :** `Node` - Le nœud racine de l'AST

**Exceptions :** `RuntimeException` - Si la requête est invalide

**Exemple :**
```php
$parser = new Parser();
$ast = $parser->parse('status=active & role=admin');
// GroupNode contenant deux ConditionNode
```

---

## Grammaire supportée

### Opérateurs de comparaison

| Opérateur | Description |
|-----------|-------------|
| `=` | Égalité (non stricte) |
| `==` | Égalité (non stricte) |
| `===` | Égalité stricte |
| `!=` | Différent (non strict) |
| `!==` | Différent strict |
| `>` | Supérieur |
| `>=` | Supérieur ou égal |
| `<` | Inférieur |
| `<=` | Inférieur ou égal |
| `<=>` | Comparaison (spaceship) |
| `=~` | LIKE (insensible à la casse) |
| `!~` | NOT LIKE (insensible à la casse) |

### Opérateurs logiques

| Opérateur | Description |
|-----------|-------------|
| `&` ou `AND` | ET logique |
| `\|` ou `OR` | OU logique |
| `NOT` ou `!` | Négation |

### Opérateurs spéciaux

| Opérateur | Description |
|-----------|-------------|
| `*` | EXISTS - Vérifie l'existence d'une clé |
| `#` | NOT_EXISTS - Vérifie l'absence d'une clé |

---

## Cas d'utilisation

### Cas 1 : Condition simple

```php
<?php

use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$parser = new Parser();

$ast = $parser->parse('status=active');
// ConditionNode avec key='status', operator=EQUAL, value='active'

$cluster = new ClusterVO(['status' => 'active']);
$result = $ast->evaluate($cluster);
// true
```

### Cas 2 : Conditions avec AND/OR

```php
// AND
$ast = $parser->parse('status=active & role=admin');
// GroupNode avec LogicalOperator::AND

// OR
$ast = $parser->parse('status=active | role=admin');
// GroupNode avec LogicalOperator::OR

// Mixte
$ast = $parser->parse('status=active & (role=admin | role=doctor)');
// GroupNode avec AND contenant un GroupNode avec OR
```

### Cas 3 : Sous-conditions

```php
// Sous-condition simple
$ast = $parser->parse('addresses[city=Kinshasa]');
// SubConditionNode avec path='addresses' et ConditionNode avec key='city'

// Sous-condition avec AND
$ast = $parser->parse('addresses[city=Kinshasa & country=RDC]');

// Sous-condition avec OR
$ast = $parser->parse('addresses[city=Kinshasa | city=Paris]');

// Sous-condition vide (vérifie l'existence du tableau)
$ast = $parser->parse('addresses[]');
// SubConditionNode avec ConditionNode '__empty__'

// Sous-condition avec wildcard (EXISTS)
$ast = $parser->parse('addresses[*]');
// SubConditionNode avec ConditionNode '*' et operator EXISTS
```

### Cas 4 : Fonctions SQL

```php
// COUNT
$ast = $parser->parse('COUNT(addresses) > 2');
// FunctionNode avec name='COUNT', path='addresses', operator=GREATER_THAN

// AVG
$ast = $parser->parse('AVG(scores) >= 85');

// Sans opérateur (COUNT > 0 par défaut)
$ast = $parser->parse('COUNT(addresses)');
// FunctionNode avec operator=GREATER_THAN, value='0'
```

### Cas 5 : Opérateurs spéciaux

```php
// EXISTS
$ast = $parser->parse('*lang_fr');
// ConditionNode avec key='lang_fr', operator=EXISTS

// NOT_EXISTS
$ast = $parser->parse('#lang_en');
// ConditionNode avec key='lang_en', operator=NOT_EXISTS

// NOT
$ast = $parser->parse('!lang_fr');
// ConditionNode avec key='lang_fr', operator=EQUAL, value='false'
```

### Cas 6 : Chemins avec indices

```php
$ast = $parser->parse('tags[0][0]=php');
// ConditionNode avec key='tags[0][0]'
```

### Cas 7 : Expressions avec guillemets

```php
$ast = $parser->parse('addresses[city="Kinshasa"]');
// Identifiant "Kinshasa" est traité comme une valeur
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fonction inconnue | `RuntimeException` | `Unknown function "{name}"` |
| Parenthèse manquante | `RuntimeException` | `Expected opening parenthesis after function name` |
| Chemin d'argument manquant | `RuntimeException` | `Expected path argument for function` |
| Parenthèse fermante manquante | `RuntimeException` | `Expected closing parenthesis` |
| Crochet fermant manquant | `RuntimeException` | `Expected closing bracket ] at position {pos}` |
| Token inattendu | `RuntimeException` | `Invalid expression at position {pos}` |
| Tokens restants | `RuntimeException` | `Unexpected tokens after expression: ...` |
| Opérateur invalide | `RuntimeException` | `Invalid operator "{op}". Allowed: ...` |

---

## Performance

- **Complexité :** O(n) où n est le nombre de tokens
- **Cache :** Les résultats sont mis en cache par requête (clé MD5)
- **Mémoire :** L'AST complet est stocké en mémoire

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

use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$parser = new Parser();

// ==================== EXEMPLES DE PARSING ====================

// 1. Condition simple
$ast = $parser->parse('status=active');
var_dump($ast instanceof ConditionNode); // true

// 2. AND
$ast = $parser->parse('status=active & role=admin');
var_dump($ast instanceof GroupNode); // true

// 3. OR
$ast = $parser->parse('status=active | role=admin');
var_dump($ast instanceof GroupNode); // true

// 4. Parenthèses
$ast = $parser->parse('(status=active | status=pending) & role=admin');

// 5. Sous-condition
$ast = $parser->parse('addresses[city=Kinshasa]');
var_dump($ast instanceof SubConditionNode); // true

// 6. Fonction SQL
$ast = $parser->parse('COUNT(addresses) > 2');
var_dump($ast instanceof FunctionNode); // true

// 7. Opérateur EXISTS
$ast = $parser->parse('*lang_fr');
var_dump($ast instanceof ConditionNode); // true

// 8. NOT
$ast = $parser->parse('!lang_fr');
var_dump($ast instanceof ConditionNode); // true

// ==================== ÉVALUATION ====================

$cluster = new ClusterVO([
    'status' => 'active',
    'role' => 'admin',
    'addresses' => [
        ['city' => 'Kinshasa'],
        ['city' => 'Paris'],
    ],
    'lang_fr' => 'true',
]);

// Évaluation d'une condition
$ast = $parser->parse('status=active');
$result = $ast->evaluate($cluster);
var_dump($result); // true

// Évaluation d'une sous-condition
$ast = $parser->parse('addresses[city=Kinshasa]');
$result = $ast->evaluate($cluster);
var_dump($result); // true

// Évaluation d'une fonction
$ast = $parser->parse('COUNT(addresses) > 1');
$result = $ast->evaluate($cluster);
var_dump($result); // true

// ==================== CACHE ====================

$ast1 = $parser->parse('status=active');
$ast2 = $parser->parse('status=active');
var_dump($ast1 === $ast2); // true (même instance)
```

---

## Voir aussi

- `Lexer` - Tokenisation de la requête
- `Node` - Interface des nœuds de l'AST
- `ConditionNode` - Nœud de condition simple
- `GroupNode` - Nœud de groupe logique
- `SubConditionNode` - Nœud de sous-condition
- `FunctionNode` - Nœud de fonction SQL