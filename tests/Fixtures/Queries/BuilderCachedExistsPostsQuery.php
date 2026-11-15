<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultExists;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class BuilderCachedExistsPostsQuery extends BuilderCachedQuery
{
    use QueryResultExists;

    protected string $model = Post::class;
}
