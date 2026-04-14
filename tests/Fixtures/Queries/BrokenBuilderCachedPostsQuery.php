<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCollection;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

#[CacheStore('array')]
class BrokenBuilderCachedPostsQuery extends BuilderCachedQuery
{
    use QueryResultCollection;

    protected string $model = PlainPost::class;
}
