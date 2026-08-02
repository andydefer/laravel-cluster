# Parser - Référence Technique

## Description

Transforme une requête textuelle en un Arbre Syntaxique Abstrait (AST) composé de nœuds (`ConditionNode`, `FunctionNode`, `GroupNode`, `SubConditionNode`).

## Hiérarchie / Implémentations

```
ParserInterface
    └── Parser
```

## Rôle principal

Le `Parser` est le cœur de l'analyse syntaxique de Laravel Cluster. Il prend une chaîne de requête (ex: `"status=active & COUNT(addresses) > 2"`) et la transforme en une structure de nœuds évaluable et transformable en SQL.

Le parser fonctionne en deux étapes :
1. **Tokenisation** : via le `Lexer`, la requête est découpée en tokens
2. **Construction AST** : les tokens sont organisés en une arborescence de nœuds

## API / Méthodes publiques

### `parse(string $query): Node`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | La requête textuelle à analyser |

**Retourne :** `Node` - Le nœud racine de l'AST (généralement un `GroupNode`, `ConditionNode` ou `FunctionNode`)

**Exceptions :** `RuntimeException` - Si la requête est invalide ou mal formée

**Exemple :**
```php
$parser = new Parser();
$ast = $parser->parse('status=active & role=admin');
// Retourne un GroupNode contenant deux ConditionNode
```

**Cache :** Les requêtes sont mises en cache par leur `md5()`. Une même requête parsée deux fois retourne la même instance.

## Cas d'utilisation

### Cas 1 : Condition simple
```php
$parser = new Parser();
$ast = $parser->parse('status=active');

// $ast est un ConditionNode
// key: 'status'
// operator: ComparisonOperator::EQUAL
// value: 'active'
```

### Cas 2 : Fonction SQL
```php
$parser = new Parser();
$ast = $parser->parse('COUNT(addresses) > 2');

// $ast est un FunctionNode
// functionName: 'COUNT'
// path: 'addresses'
// operator: ComparisonOperator::GREATER_THAN
// value: '2'
```

### Cas 3 : Fonction CONTAINS
```php
$parser = new Parser();
$ast = $parser->parse('CONTAINS(languages, fr)');

// $ast est un FunctionNode
// functionName: 'CONTAINS'
// path: 'languages'
// args: ['languages', 'fr']
// operator: ComparisonOperator::GREATER_THAN (défaut)
// value: '0' (défaut)
```

### Cas 4 : Condition avec opérateur
```php
$parser = new Parser();
$ast = $parser->parse('CONTAINS(languages, fr) = true');

// $ast est un FunctionNode
// functionName: 'CONTAINS'
// path: 'languages'
// args: ['languages', 'fr']
// operator: ComparisonOperator::EQUAL
// value: 'true'
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Requête vide | `RuntimeException` | `Unexpected end of expression` |
| Opérateur invalide | `RuntimeException` | `Invalid operator "X". Allowed: =, !=, >, <, >=, <=, ===, !==, <=>` |
| Parenthèse fermante manquante | `RuntimeException` | `Expected closing parenthesis` |
| Parenthèse ouvrante manquante | `RuntimeException` | `Expected opening parenthesis after function name` |
| Fonction inconnue | `RuntimeException` | `Unknown function "X"` |
| Trop peu d'arguments | `RuntimeException` | `Function "X" expects at least N arguments, M given` |
| Trop d'arguments | `RuntimeException` | `Function "X" expects at most N arguments, M given` |
| Arguments invalides | `RuntimeException` | `Invalid arguments for function "X"` |
| Caractère invalide | `InvalidArgumentException` | `Invalid character "X" at position N` |
| Crochet fermant manquant | `RuntimeException` | `Expected closing bracket ] at position N, got: X` |
| Tokens inattendus | `RuntimeException` | `Unexpected tokens after expression: X` |

## Intégration

Le `Parser` fonctionne en étroite collaboration avec :

- **`Lexer`** : Fournit les tokens à analyser
- **`SqlFunctionRegistry`** : Valide les fonctions SQL et leurs arguments
- **`Node`** : Les différents types de nœuds (ConditionNode, FunctionNode, GroupNode, SubConditionNode)

### Cycle de vie d'une requête

```
Requête textuelle
    ↓
Parser::parse()
    ↓
Lexer::tokenize() → TokenRecordCollection
    ↓
parseExpression() → parseTerm() → ...
    ↓
Construction de l'AST (GroupNode, ConditionNode, FunctionNode, SubConditionNode)
    ↓
Cache (stocké par md5)
    ↓
Retourne le nœud racine
```

## Performance

- **Cache** : Les requêtes sont mises en cache par `md5()`. Une même requête parsée plusieurs fois retourne la même instance, évitant une re-tokenisation et re-construction.
- **Complexité** : O(n) où n est le nombre de tokens.
- **Mémoire** : L'AST est stocké en cache. Pour des requêtes uniques, l'AST est conservé en mémoire.

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

$parser = new Parser();

// 1. Condition simple
$ast = $parser->parse('status=active');
$cluster = new ClusterVO(['status' => 'active']);
var_dump($ast->evaluate($cluster)); // bool(true)

// 2. Condition avec AND
$ast = $parser->parse('status=active & role=admin');
$cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);
var_dump($ast->evaluate($cluster)); // bool(true)

// 3. Fonction COUNT
$ast = $parser->parse('COUNT(addresses) > 2');
$cluster = new ClusterVO(['addresses' => ['a', 'b', 'c']]);
var_dump($ast->evaluate($cluster)); // bool(true)

// 4. Fonction CONTAINS
$ast = $parser->parse('CONTAINS(languages, fr)');
$cluster = new ClusterVO(['languages' => ['fr', 'en', 'es']]);
var_dump($ast->evaluate($cluster)); // bool(true)

// 5. Fonction CONTAINS avec opérateur
$ast = $parser->parse('CONTAINS(languages, fr) = true');
$cluster = new ClusterVO(['languages' => ['fr', 'en']]);
var_dump($ast->evaluate($cluster)); // bool(true)

// 6. Sous-condition
$ast = $parser->parse('addresses[city=Kinshasa]');
$cluster = new ClusterVO([
    'addresses' => [
        ['city' => 'Kinshasa'],
        ['city' => 'Paris'],
    ]
]);
var_dump($ast->evaluate($cluster)); // bool(true)
```

## Voir aussi

- [`Lexer`](Lexer.md) - Analyse lexicale des requêtes
- [`SqlFunctionRegistry`](Registry/SqlFunctionRegistry.md) - Registre des fonctions SQL
- [`ConditionNode`](Nodes/ConditionNode.md) - Nœud représentant une condition
- [`FunctionNode`](Nodes/FunctionNode.md) - Nœud représentant une fonction SQL
- [`GroupNode`](Nodes/GroupNode.md) - Nœud représentant un groupe logique (AND/OR)
- [`SubConditionNode`](Nodes/SubConditionNode.md) - Nœud représentant une sous-condition