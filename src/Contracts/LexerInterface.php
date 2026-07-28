<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Contracts;

use AndyDefer\LaravelCluster\Collections\TokenRecordCollection;

interface LexerInterface
{
    public function tokenize(string $input): TokenRecordCollection;
}
