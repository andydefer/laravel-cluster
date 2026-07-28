<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster;

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;
use AndyDefer\LaravelCluster\Contracts\LexerInterface;
use AndyDefer\LaravelCluster\Enums\OperatorToken;
use AndyDefer\LaravelCluster\Enums\TokenType;
use AndyDefer\LaravelCluster\Records\TokenRecord;
use InvalidArgumentException;

/**
 * Converts a cluster query string into a stream of tokens.
 *
 * The Lexer scans the input string character by character, identifying:
 * - Parentheses: '(' and ')'
 * - Operators: =, !=, >, <, >=, <=, LIKE, NOT LIKE, etc.
 * - Identifiers: alphanumeric strings, underscores, and hyphens
 * - LIKE pattern values: values containing '%' wildcards
 *
 * @example
 * $lexer = new Lexer();
 * $tokens = $lexer->tokenize('age > 18 AND status = "active"');
 * // Returns a collection of TokenRecord objects
 */
final class Lexer implements LexerInterface
{
    private string $input;

    private int $position = 0;

    private int $length = 0;

    private bool $isLikeValueMode = false;

    /**
     * Tokenizes the input string into a collection of tokens.
     *
     * The tokenization process:
     * 1. Skips whitespace characters
     * 2. Identifies parentheses
     * 3. Matches operator tokens (longest match first)
     * 4. Reads identifiers and LIKE pattern values
     *
     * @param  string  $input  The query string to tokenize
     * @return TokenRecordCollection The collection of tokens
     *
     * @throws InvalidArgumentException If an invalid character is encountered
     */
    public function tokenize(string $input): TokenRecordCollection
    {
        $this->initializeLexerState($input);
        $tokens = new TokenRecordCollection;

        while ($this->position < $this->length) {
            $currentChar = $this->input[$this->position];

            if ($this->isWhitespace($currentChar)) {
                $this->advancePosition();
                $this->isLikeValueMode = false;

                continue;
            }

            if ($this->isParenthesis($currentChar)) {
                $tokens->add($this->createParenthesisToken($currentChar));
                $this->advancePosition();
                $this->isLikeValueMode = false;

                continue;
            }

            $operatorToken = $this->matchOperatorToken();
            if ($operatorToken !== null) {
                $tokens->add($this->createOperatorToken($operatorToken));
                $this->position += strlen($operatorToken->value);
                $this->isLikeValueMode = $operatorToken->isLike();

                continue;
            }

            if ($this->isIdentifierStart($currentChar) || ($this->isLikeValueMode && $currentChar === '%')) {
                $tokens->add($this->createIdentifierToken());
                $this->isLikeValueMode = false;

                continue;
            }

            throw new InvalidArgumentException(
                sprintf('Invalid character "%s" at position %d', $currentChar, $this->position)
            );
        }

        $tokens->add($this->createEndToken());

        return $tokens;
    }

    /**
     * Initializes the lexer state for a new tokenization session.
     *
     * @param  string  $input  The input string to tokenize
     */
    private function initializeLexerState(string $input): void
    {
        $this->input = $input;
        $this->position = 0;
        $this->length = strlen($input);
        $this->isLikeValueMode = false;
    }

    /**
     * Advances the current position by one character.
     */
    private function advancePosition(): void
    {
        $this->position++;
    }

    /**
     * Creates a parenthesis token record.
     *
     * @param  string  $char  The parenthesis character ('(' or ')')
     * @return TokenRecord The created token
     */
    private function createParenthesisToken(string $char): TokenRecord
    {
        return new TokenRecord(
            TokenType::PAREN,
            $char,
            $this->position
        );
    }

    /**
     * Creates an operator token record.
     *
     * @param  OperatorToken  $operatorToken  The operator token enum
     * @return TokenRecord The created token
     */
    private function createOperatorToken(OperatorToken $operatorToken): TokenRecord
    {
        return new TokenRecord(
            TokenType::OPERATOR,
            $operatorToken->getValue(),
            $this->position
        );
    }

    /**
     * Creates an identifier token record.
     *
     * @return TokenRecord The created token
     */
    private function createIdentifierToken(): TokenRecord
    {
        return new TokenRecord(
            TokenType::IDENTIFIER,
            $this->readIdentifierOrLikeValue(),
            $this->position
        );
    }

    /**
     * Creates an end-of-input token record.
     *
     * @return TokenRecord The end token
     */
    private function createEndToken(): TokenRecord
    {
        return new TokenRecord(TokenType::END, '', $this->position);
    }

    /**
     * Checks if a character is whitespace.
     *
     * @param  string  $char  The character to check
     * @return bool True if the character is whitespace
     */
    private function isWhitespace(string $char): bool
    {
        return ctype_space($char);
    }

    /**
     * Checks if a character is a parenthesis.
     *
     * @param  string  $char  The character to check
     * @return bool True if the character is '(' or ')'
     */
    private function isParenthesis(string $char): bool
    {
        return $char === '(' || $char === ')';
    }

    /**
     * Checks if a character can start an identifier.
     *
     * @param  string  $char  The character to check
     * @return bool True if the character is alphanumeric, '_', or '-'
     */
    private function isIdentifierStart(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-';
    }

    /**
     * Matches an operator token at the current position.
     *
     * Uses longest-match-first strategy to handle multi-character operators
     * like '!=' and '>=' before single-character operators.
     *
     * @return OperatorToken|null The matched operator token or null if none found
     */
    private function matchOperatorToken(): ?OperatorToken
    {
        $symbols = OperatorToken::symbols();
        usort($symbols, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($symbols as $symbol) {
            if (substr($this->input, $this->position, strlen($symbol)) === $symbol) {
                return OperatorToken::fromSymbol($symbol);
            }
        }

        return null;
    }

    /**
     * Reads an identifier or a LIKE pattern value.
     *
     * In LIKE value mode, allows '%' wildcard characters.
     * Otherwise, reads standard identifier characters.
     *
     * @return string The read value
     */
    private function readIdentifierOrLikeValue(): string
    {
        $value = '';

        while ($this->position < $this->length) {
            $char = $this->input[$this->position];

            $isValidChar = ctype_alnum($char)
                || $char === '_'
                || $char === '-'
                || ($this->isLikeValueMode && $char === '%');

            if (! $isValidChar) {
                break;
            }

            $value .= $char;
            $this->advancePosition();
        }

        return $value;
    }

    /**
     * Reads a standard identifier (without LIKE pattern support).
     *
     * @return string The read identifier
     *
     * @deprecated This method is not used in the current implementation
     */
    private function readIdentifier(): string
    {
        $value = '';

        while ($this->position < $this->length) {
            $char = $this->input[$this->position];

            $isValidChar = ctype_alnum($char)
                || $char === '_'
                || $char === '-';

            if (! $isValidChar) {
                break;
            }

            $value .= $char;
            $this->advancePosition();
        }

        return $value;
    }
}
