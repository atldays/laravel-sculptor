<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultAll;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class BuilderCachedAllPostsQuery extends BuilderCachedQuery
{
    use QueryResultAll;

    protected string $model = Post::class;
}
