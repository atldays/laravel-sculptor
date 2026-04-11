<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

class StringSelectPostsQuery extends PostsQuery
{
    protected string $select = 'posts.title';
}
