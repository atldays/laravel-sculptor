<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Concerns\HasModel;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

#[ForModel(Post::class)]
class AttributeModelConsumer
{
    use HasModel;
}
