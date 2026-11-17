<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Concerns\HasModel;
use Atldays\Sculptor\Tests\Fixtures\Models\{PlainPost, Post};

#[ForModel(PlainPost::class)]
class ModelPropertyOverridesAttributeConsumer
{
    use HasModel;

    protected string $model = Post::class;
}
