<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Contracts\NodeInterface;

abstract class Node implements NodeInterface
{
    /**
     * @return array<int, NodeInterface>
     */
    public function getChildren(): array
    {
        return [];
    }
}
