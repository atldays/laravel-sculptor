<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\QueryResultCollection;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class PostsQuery extends Query
{
    use QueryResultCollection;

    protected string $model = Post::class;

    protected array $with = ['author'];
}
