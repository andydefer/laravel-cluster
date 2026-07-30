<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class TestCluster extends Model
{
    protected $table = 'test_clusters';

    protected $fillable = [
        'name',
        'email',
        'clusters',
    ];

    protected $casts = [
        'clusters' => 'array',
    ];
}
