<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCount;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class CachedCountPostsQuery extends CachedQuery
{
    use QueryResultCount;

    protected string $model = PlainPost::class;
}
