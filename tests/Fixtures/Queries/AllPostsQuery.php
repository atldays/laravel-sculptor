<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\QueryResultAll;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class AllPostsQuery extends Query
{
    use QueryResultAll;

    protected string $model = PlainPost::class;
}
