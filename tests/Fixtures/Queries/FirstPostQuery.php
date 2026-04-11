<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\QueryResultFirst;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class FirstPostQuery extends Query
{
    use QueryResultFirst;

    protected string $model = Post::class;
}
