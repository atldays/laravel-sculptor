<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultExists;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class CachedExistsPostsQuery extends CachedQuery
{
    use QueryResultExists;

    protected string $model = PlainPost::class;
}
