<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\QueryResultCursorPaginated;
use Atldays\Sculptor\Contracts\WithCursorPaginatedResult;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class CursorPaginatedPostsQuery extends Query implements WithCursorPaginatedResult
{
    use QueryResultCursorPaginated;

    protected string $model = PlainPost::class;
}
