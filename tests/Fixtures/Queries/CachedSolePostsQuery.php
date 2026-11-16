<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultSole;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class CachedSolePostsQuery extends CachedQuery
{
    use QueryResultSole;

    protected string $model = PlainPost::class;
}
