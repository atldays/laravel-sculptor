<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\{CacheFor, CacheScope};
use Illuminate\Contracts\Database\Eloquent\Builder;

#[CacheFor(600)]
class AttributeTtlPostsQuery extends CachedAllPostsQuery
{
    public function __construct(#[CacheScope] private readonly int $ownerId = 42) {}

    public function query(): Builder
    {
        return parent::query()->where('author_id', $this->ownerId);
    }
}
