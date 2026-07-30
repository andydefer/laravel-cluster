<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Exceptions;

/**
 * Exception thrown when parsing an aggregate expression fails.
 */
class ParseException extends \Exception
{
    private string $expression;

    private int $position;

    public function __construct(string $message, string $expression, int $position = 0, ?\Throwable $previous = null)
    {
        $this->expression = $expression;
        $this->position = $position;

        $fullMessage = $message;
        if ($position > 0) {
            $fullMessage .= sprintf(' at position %d', $position);
        }
        $fullMessage .= sprintf(' in expression: "%s"', $expression);

        // Add context with arrow pointing to error position
        if ($position > 0 && $position < strlen($expression)) {
            $fullMessage .= "\n".$expression;
            $fullMessage .= "\n".str_repeat(' ', $position).'^';
        }

        parent::__construct($fullMessage, 0, $previous);
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
