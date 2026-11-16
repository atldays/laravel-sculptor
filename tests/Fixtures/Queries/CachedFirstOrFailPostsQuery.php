<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultFirstOrFail;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;

class CachedFirstOrFailPostsQuery extends CachedQuery
{
    use QueryResultFirstOrFail;

    protected string $model = PlainPost::class;
}
