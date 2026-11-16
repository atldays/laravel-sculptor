<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultSole;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class BuilderCachedSolePostsQuery extends BuilderCachedQuery
{
    use QueryResultSole;

    protected string $model = Post::class;
}
