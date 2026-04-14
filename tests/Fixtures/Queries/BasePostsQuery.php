<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\BaseQuery;
use Atldays\Sculptor\Concerns\QueryResultCollection;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class BasePostsQuery extends BaseQuery
{
    use QueryResultCollection;

    protected string $model = Post::class;

    protected array $with = ['author'];
}
