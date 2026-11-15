<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\QueryResultCount;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class CountPostsQuery extends Query
{
    use QueryResultCount;

    protected string $model = PlainPost::class;
}
