<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCursorPaginated;
use Atldays\Sculptor\Contracts\WithCursorPaginatedResult;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class CachedCursorPaginatedPostsQuery extends CachedQuery implements WithCursorPaginatedResult
{
    use QueryResultCursorPaginated;

    protected string $model = PlainPost::class;
}
