<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Select;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

#[ForModel(Post::class)]
#[Select('posts.title')]
class AttributeStringSelectPostsQuery extends Query {}
