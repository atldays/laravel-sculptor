<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

class ArraySelectPostsQuery extends PostsQuery
{
    protected array $select = [
        'posts.id',
        'posts.title',
    ];
}
