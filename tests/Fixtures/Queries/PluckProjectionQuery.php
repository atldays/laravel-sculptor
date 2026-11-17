<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\{ForModel, PluckKey, ResultColumn};
use Atldays\Sculptor\Concerns\QueryResultPluck;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\ProjectionPost;

#[ForModel(ProjectionPost::class)]
#[ResultColumn('posts.title')]
#[PluckKey('posts.id')]
class PluckProjectionQuery extends Query
{
    use QueryResultPluck;
}
