<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\Limit;

#[Limit(2)]
class AttributeAllPostsQuery extends AllPostsQuery {}
