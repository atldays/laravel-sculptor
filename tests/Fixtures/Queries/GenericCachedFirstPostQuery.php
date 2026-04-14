<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultFirst;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

#[CacheStore('array')]
class GenericCachedFirstPostQuery extends CachedQuery
{
    use QueryResultFirst;

    protected string $model = Post::class;
}
