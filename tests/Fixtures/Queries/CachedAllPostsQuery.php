<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultAll;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class CachedAllPostsQuery extends CachedQuery
{
    use QueryResultAll;

    protected string $model = PlainPost::class;
}
