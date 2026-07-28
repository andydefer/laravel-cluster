<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class ClusterVOCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ClusterVO::class);
    }
}
