<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultSimplePaginated;
use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class CachedSimplePaginatedPostsQuery extends CachedQuery implements WithPaginatedResult
{
    use QueryResultSimplePaginated;

    protected string $model = PlainPost::class;
}
