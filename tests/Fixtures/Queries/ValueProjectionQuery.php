<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\{ForModel, ResultColumn};
use Atldays\Sculptor\Concerns\QueryResultValue;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\ProjectionPost;

#[ForModel(ProjectionPost::class)]
#[ResultColumn('posts.title')]
class ValueProjectionQuery extends Query
{
    use QueryResultValue;
}
