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
 * Lexer for parsing query expressions into tokens.
 *
 * This class transforms a raw query string into a stream of tokens
 * that can be consumed by the parser. It handles:
 * - Identifiers (keys, paths, function names)
 * - Operators (comparison, logical)
 * - Parentheses and brackets
 * - Quoted strings (single, double, backtick)
 * - LIKE patterns with % wildcards
 *
 * @example
 * $lexer = new Lexer();
 * $tokens = $lexer->tokenize('status=active & COUNT(addresses) > 2');
 * // [IDENTIFIER:status, OPERATOR:=, IDENTIFIER:active, OPERATOR:&, ...]
 * @example
 * $tokens = $lexer->tokenize('addresses[city="Kinshasa"]');
 * // [IDENTIFIER:addresses, SUB_OPEN:[, IDENTIFIER:city, OPERATOR:=, IDENTIFIER:Kinshasa, SUB_CLOSE:]]
 */
final class Lexer implements LexerInterface
{
    private string $input;

    private int $position = 0;

    private int $length = 0;

    private bool $isLikeValueMode = false;

    private bool $inSubBracket = false;

    private bool $inQuotes = false;

    private string $quoteChar = '';

    /**
     * Tokenizes the input string into a collection of tokens.
     *
     * @param  string  $input  The query string to tokenize
     * @return TokenRecordCollection The collection of tokens
     *
     * @throws InvalidArgumentException When an invalid character is encountered
     */
    public function tokenize(string $input): TokenRecordCollection
    {
        $this->initializeLexerState($input);
        $tokens = new TokenRecordCollection;

        while ($this->position < $this->length) {
            $currentChar = $this->input[$this->position];

            if ($this->isDelimiter($currentChar)) {
                $this->handleDelimiter($currentChar, $tokens);

                continue;
            }

            if ($this->isWhitespace($currentChar)) {
                $this->advancePosition();
                $this->isLikeValueMode = false;

                continue;
            }

            if ($this->isParenthesis($currentChar)) {
                $tokens->add($this->createParenthesisToken($currentChar));
                $this->advancePosition();
                $this->isLikeValueMode = false;
                $this->inSubBracket = false;

                continue;
            }

            if ($this->isBracket($currentChar)) {
                $tokens->add($this->createBracketToken($currentChar));
                $this->advancePosition();
                $this->isLikeValueMode = false;
                $this->inSubBracket = $currentChar === '[';

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

        if ($this->inQuotes) {
            $tokens->add($this->createIdentifierToken());
        }

        $tokens->add($this->createEndToken());

        return $tokens;
    }

    /**
     * Initializes the lexer state for a new tokenization.
     *
     * @param  string  $input  The input string to tokenize
     */
    private function initializeLexerState(string $input): void
    {
        $this->input = $input;
        $this->position = 0;
        $this->length = strlen($input);
        $this->isLikeValueMode = false;
        $this->inSubBracket = false;
        $this->inQuotes = false;
        $this->quoteChar = '';
    }

    /**
     * Advances the current position by one character.
     */
    private function advancePosition(): void
    {
        $this->position++;
    }

    /**
     * Checks if a character is a string delimiter (quote or backtick).
     *
     * @param  string  $char  The character to check
     * @return bool True if the character is a delimiter
     */
    private function isDelimiter(string $char): bool
    {
        return $char === '"' || $char === "'" || $char === '`';
    }

    /**
     * Handles string delimiters (quotes and backticks).
     *
     * @param  string  $char  The delimiter character
     * @param  TokenRecordCollection  $tokens  The token collection
     */
    private function handleDelimiter(string $char, TokenRecordCollection $tokens): void
    {
        if (! $this->inQuotes) {
            $this->inQuotes = true;
            $this->quoteChar = $char;
            $this->advancePosition();

            return;
        }

        if ($this->inQuotes && $char === $this->quoteChar) {
            $this->inQuotes = false;
            $this->quoteChar = '';
            $this->advancePosition();

            return;
        }

        $this->advancePosition();
    }

    /**
     * Creates a parenthesis token.
     *
     * @param  string  $char  The parenthesis character
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
     * Creates a bracket token (sub-condition open or close).
     *
     * @param  string  $char  The bracket character
     * @return TokenRecord The created token
     */
    private function createBracketToken(string $char): TokenRecord
    {
        $type = $char === '[' ? TokenType::SUB_OPEN : TokenType::SUB_CLOSE;

        return new TokenRecord(
            $type,
            $char,
            $this->position
        );
    }

    /**
     * Creates an operator token.
     *
     * @param  OperatorToken  $operatorToken  The operator to tokenize
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
     * Creates an identifier token by reading the current identifier.
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
     * Creates an end-of-input token.
     *
     * @return TokenRecord The created token
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
     * @return bool True if the character is a parenthesis
     */
    private function isParenthesis(string $char): bool
    {
        return $char === '(' || $char === ')';
    }

    /**
     * Checks if a character is a bracket.
     *
     * @param  string  $char  The character to check
     * @return bool True if the character is a bracket
     */
    private function isBracket(string $char): bool
    {
        return $char === '[' || $char === ']';
    }

    /**
     * Determines if a character can start an identifier.
     *
     * @param  string  $char  The character to check
     * @return bool True if the character can start an identifier
     */
    private function isIdentifierStart(string $char): bool
    {
        if ($this->inQuotes) {
            return true;
        }

        if ($this->inSubBracket && $char === '*') {
            return true;
        }

        return ctype_alnum($char) || $char === '_' || $char === '-' || $char === '.';
    }

    /**
     * Attempts to match an operator token at the current position.
     *
     * @return OperatorToken|null The matched operator, or null if none found
     */
    private function matchOperatorToken(): ?OperatorToken
    {
        $symbols = OperatorToken::symbols();
        usort($symbols, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($symbols as $symbol) {
            if ($this->inSubBracket && $symbol === '*') {
                continue;
            }
            if (substr($this->input, $this->position, strlen($symbol)) === $symbol) {
                return OperatorToken::fromSymbol($symbol);
            }
        }

        return null;
    }

    /**
     * Reads an identifier or LIKE pattern value from the current position.
     *
     * @return string The read value
     */
    private function readIdentifierOrLikeValue(): string
    {
        $value = '';

        while ($this->position < $this->length) {
            $char = $this->input[$this->position];

            if ($this->inQuotes) {
                if ($char === $this->quoteChar) {
                    break;
                }
                $value .= $char;
                $this->advancePosition();

                continue;
            }

            $isValidChar = ctype_alnum($char)
                || $char === '_'
                || $char === '-'
                || $char === '.'
                || ($this->isLikeValueMode && $char === '%')
                || ($this->inSubBracket && $char === '*')
                || ($this->inQuotes);

            if (! $isValidChar) {
                break;
            }

            $value .= $char;
            $this->advancePosition();
        }

        return $value;
    }
}
