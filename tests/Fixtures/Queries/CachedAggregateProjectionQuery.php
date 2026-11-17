<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\{Aggregate, ForModel, ResultColumn};
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultAggregate;
use Atldays\Sculptor\Enums\AggregateFunction;
use Atldays\Sculptor\Tests\Fixtures\Models\ProjectionPost;

#[ForModel(ProjectionPost::class)]
#[ResultColumn('posts.id')]
#[Aggregate(AggregateFunction::Sum)]
class CachedAggregateProjectionQuery extends CachedQuery
{
    use QueryResultAggregate;
}
