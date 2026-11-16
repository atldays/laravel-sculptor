<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\ResultColumn;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultValue;
use Atldays\Sculptor\Tests\Fixtures\Models\ProjectionPost;

#[ForModel(ProjectionPost::class)]
#[ResultColumn('posts.title')]
class CachedValueProjectionQuery extends CachedQuery
{
    use QueryResultValue;
}
