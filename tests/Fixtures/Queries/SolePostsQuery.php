<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\QueryResultSole;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class SolePostsQuery extends Query
{
    use QueryResultSole;

    protected string $model = PlainPost::class;
}
