<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster;

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;
use AndyDefer\LaravelCluster\Contracts\LexerInterface;
use AndyDefer\LaravelCluster\Enums\OperatorToken;
use AndyDefer\LaravelCluster\Enums\TokenType;
use AndyDefer\LaravelCluster\Records\TokenRecord;

final class Lexer implements LexerInterface
{
    private string $input;

    private int $position = 0;

    private int $length = 0;

    private bool $isLikeValue = false;

    public function tokenize(string $input): TokenRecordCollection
    {
        $this->input = $input;
        $this->position = 0;
        $this->length = strlen($input);
        $this->isLikeValue = false;

        $tokens = new TokenRecordCollection;

        while ($this->position < $this->length) {
            $char = $this->input[$this->position];

            if ($this->isWhitespace($char)) {
                $this->position++;
                $this->isLikeValue = false;

                continue;
            }

            if ($this->isParen($char)) {
                $tokens->add(new TokenRecord(TokenType::PAREN, $char, $this->position));
                $this->position++;
                $this->isLikeValue = false;

                continue;
            }

            $operatorToken = $this->matchOperatorToken();
            if ($operatorToken !== null) {
                $tokens->add(new TokenRecord(
                    TokenType::OPERATOR,
                    $operatorToken->getValue(),
                    $this->position
                ));
                $this->position += strlen($operatorToken->value);
                // Si c'est un opérateur LIKE ou NOT_LIKE, activer le mode valeur LIKE
                $this->isLikeValue = $operatorToken->isLike();

                continue;
            }

            if ($this->isIdentifierStart($char) || ($this->isLikeValue && $char === '%')) {
                $tokens->add(new TokenRecord(
                    TokenType::IDENTIFIER,
                    $this->readIdentifierOrLikeValue(),
                    $this->position
                ));
                $this->isLikeValue = false;

                continue;
            }

            throw new \InvalidArgumentException(
                sprintf('Invalid character "%s" at position %d', $char, $this->position)
            );
        }

        $tokens->add(new TokenRecord(TokenType::END, '', $this->position));

        return $tokens;
    }

    private function isWhitespace(string $char): bool
    {
        return ctype_space($char);
    }

    private function isParen(string $char): bool
    {
        return $char === '(' || $char === ')';
    }

    private function isIdentifierStart(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-';
    }

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

    private function readIdentifierOrLikeValue(): string
    {
        $value = '';

        while (
            $this->position < $this->length &&
            (
                ctype_alnum($this->input[$this->position]) ||
                $this->input[$this->position] === '_' ||
                $this->input[$this->position] === '-' ||
                ($this->isLikeValue && $this->input[$this->position] === '%')
            )
        ) {
            $value .= $this->input[$this->position];
            $this->position++;
        }

        return $value;
    }

    private function readIdentifier(): string
    {
        $value = '';

        while (
            $this->position < $this->length &&
            (
                ctype_alnum($this->input[$this->position]) ||
                $this->input[$this->position] === '_' ||
                $this->input[$this->position] === '-'
            )
        ) {
            $value .= $this->input[$this->position];
            $this->position++;
        }

        return $value;
    }
}
