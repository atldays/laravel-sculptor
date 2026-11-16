<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCursorPaginated;
use Atldays\Sculptor\Contracts\WithCursorPaginatedResult;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class BuilderCachedCursorPaginatedPostsQuery extends BuilderCachedQuery implements WithCursorPaginatedResult
{
    use QueryResultCursorPaginated;

    protected string $model = Post::class;
}
