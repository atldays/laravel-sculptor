<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\QueryResultExists;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class ExistsPostsQuery extends Query
{
    use QueryResultExists;

    protected string $model = PlainPost::class;
}
