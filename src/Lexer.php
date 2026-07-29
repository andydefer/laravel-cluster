<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster;

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;
use AndyDefer\LaravelCluster\Contracts\LexerInterface;
use AndyDefer\LaravelCluster\Enums\OperatorToken;
use AndyDefer\LaravelCluster\Enums\TokenType;
use AndyDefer\LaravelCluster\Records\TokenRecord;
use InvalidArgumentException;

final class Lexer implements LexerInterface
{
    private string $input;

    private int $position = 0;

    private int $length = 0;

    private bool $isLikeValueMode = false;

    private bool $inSubBracket = false;

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

        $tokens->add($this->createEndToken());

        return $tokens;
    }

    private function initializeLexerState(string $input): void
    {
        $this->input = $input;
        $this->position = 0;
        $this->length = strlen($input);
        $this->isLikeValueMode = false;
        $this->inSubBracket = false;
    }

    private function advancePosition(): void
    {
        $this->position++;
    }

    private function createParenthesisToken(string $char): TokenRecord
    {
        return new TokenRecord(
            TokenType::PAREN,
            $char,
            $this->position
        );
    }

    private function createBracketToken(string $char): TokenRecord
    {
        $type = $char === '[' ? TokenType::SUB_OPEN : TokenType::SUB_CLOSE;

        return new TokenRecord(
            $type,
            $char,
            $this->position
        );
    }

    private function createOperatorToken(OperatorToken $operatorToken): TokenRecord
    {
        return new TokenRecord(
            TokenType::OPERATOR,
            $operatorToken->getValue(),
            $this->position
        );
    }

    private function createIdentifierToken(): TokenRecord
    {
        return new TokenRecord(
            TokenType::IDENTIFIER,
            $this->readIdentifierOrLikeValue(),
            $this->position
        );
    }

    private function createEndToken(): TokenRecord
    {
        return new TokenRecord(TokenType::END, '', $this->position);
    }

    private function isWhitespace(string $char): bool
    {
        return ctype_space($char);
    }

    private function isParenthesis(string $char): bool
    {
        return $char === '(' || $char === ')';
    }

    private function isBracket(string $char): bool
    {
        return $char === '[' || $char === ']';
    }

    private function isIdentifierStart(string $char): bool
    {
        if ($this->inSubBracket && $char === '*') {
            return true;
        }

        return ctype_alnum($char) || $char === '_' || $char === '-' || $char === '.';
    }

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

    private function readIdentifierOrLikeValue(): string
    {
        $value = '';

        while ($this->position < $this->length) {
            $char = $this->input[$this->position];

            $isValidChar = ctype_alnum($char)
                || $char === '_'
                || $char === '-'
                || $char === '.'
                || ($this->isLikeValueMode && $char === '%')
                || ($this->inSubBracket && $char === '*');

            if (! $isValidChar) {
                break;
            }

            $value .= $char;
            $this->advancePosition();
        }

        return $value;
    }
}
