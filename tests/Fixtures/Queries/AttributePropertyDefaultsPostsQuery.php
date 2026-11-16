<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\{ForModel, Select, WithRelations};
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\{PlainPost, Post};

#[ForModel(PlainPost::class)]
#[Select(['posts.id'])]
#[WithRelations(['missing'])]
class AttributePropertyDefaultsPostsQuery extends Query
{
    protected string $model = Post::class;

    protected string $select = 'posts.title';

    protected array $with = ['author'];
}
