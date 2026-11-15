<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultFirstOrFail;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class BuilderCachedFirstOrFailPostsQuery extends BuilderCachedQuery
{
    use QueryResultFirstOrFail;

    protected string $model = Post::class;
}
