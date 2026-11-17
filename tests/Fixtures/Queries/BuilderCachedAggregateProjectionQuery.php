<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\{Aggregate, ForModel, ResultColumn};
use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultAggregate;
use Atldays\Sculptor\Enums\AggregateFunction;
use Atldays\Sculptor\Tests\Fixtures\Models\CacheableProjectionPost;

#[ForModel(CacheableProjectionPost::class)]
#[ResultColumn('posts.id')]
#[Aggregate(AggregateFunction::Sum)]
class BuilderCachedAggregateProjectionQuery extends BuilderCachedQuery
{
    use QueryResultAggregate;
}
