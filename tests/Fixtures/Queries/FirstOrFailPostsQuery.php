<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\QueryResultFirstOrFail;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class FirstOrFailPostsQuery extends Query
{
    use QueryResultFirstOrFail;

    protected string $model = PlainPost::class;
}
