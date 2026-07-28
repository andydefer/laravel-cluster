<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Contracts;

use AndyDefer\LaravelCluster\Nodes\Node;

interface ParserInterface
{
    public function parse(string $query): Node;
}
