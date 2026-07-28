<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelCluster\Enums\TokenType;

final class TokenRecord extends AbstractRecord
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly int $position
    ) {}
}
