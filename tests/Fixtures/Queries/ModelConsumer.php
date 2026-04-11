<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\HasModel;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

class ModelConsumer
{
    use HasModel;

    protected string $model = Post::class;
}
