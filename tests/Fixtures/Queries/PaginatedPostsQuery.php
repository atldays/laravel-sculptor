<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\QueryResultPaginated;
use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class PaginatedPostsQuery extends Query implements WithPaginatedResult
{
    use QueryResultPaginated;

    protected string $model = Post::class;
}
