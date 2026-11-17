<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\{ForModel, ResultColumn};
use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultValue;
use Atldays\Sculptor\Tests\Fixtures\Models\CacheableProjectionPost;

#[ForModel(CacheableProjectionPost::class)]
#[ResultColumn('posts.title')]
class BuilderCachedValueProjectionQuery extends BuilderCachedQuery
{
    use QueryResultValue;
}
