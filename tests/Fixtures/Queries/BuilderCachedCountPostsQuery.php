<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCount;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class BuilderCachedCountPostsQuery extends BuilderCachedQuery
{
    use QueryResultCount;

    protected string $model = Post::class;
}
