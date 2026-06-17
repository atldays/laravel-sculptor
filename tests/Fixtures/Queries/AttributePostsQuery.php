<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\Attributes\Select;
use Atldays\Sculptor\Attributes\WithRelations;
use Atldays\Sculptor\Concerns\QueryResultCollection;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

#[ForModel(Post::class)]
#[Select(['posts.id', 'posts.title', 'posts.author_id'])]
#[WithRelations(['author'])]
#[Limit(10)]
class AttributePostsQuery extends Query
{
    use QueryResultCollection;
}
