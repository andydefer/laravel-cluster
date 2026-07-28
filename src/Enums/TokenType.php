<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

enum TokenType
{
    case IDENTIFIER;
    case OPERATOR;
    case PAREN;
    case END;
}