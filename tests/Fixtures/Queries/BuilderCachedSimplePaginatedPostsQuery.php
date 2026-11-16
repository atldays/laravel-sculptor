<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultSimplePaginated;
use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class BuilderCachedSimplePaginatedPostsQuery extends BuilderCachedQuery implements WithPaginatedResult
{
    use QueryResultSimplePaginated;

    protected string $model = Post::class;
}
