<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\QueryResultSimplePaginated;
use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class SimplePaginatedPostsQuery extends Query implements WithPaginatedResult
{
    use QueryResultSimplePaginated;

    protected string $model = PlainPost::class;
}
