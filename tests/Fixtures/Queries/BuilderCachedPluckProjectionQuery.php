<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\PluckKey;
use Atldays\Sculptor\Attributes\ResultColumn;
use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultPluck;
use Atldays\Sculptor\Tests\Fixtures\Models\CacheableProjectionPost;

#[ForModel(CacheableProjectionPost::class)]
#[ResultColumn('posts.title')]
#[PluckKey('posts.id')]
class BuilderCachedPluckProjectionQuery extends BuilderCachedQuery
{
    use QueryResultPluck;
}
