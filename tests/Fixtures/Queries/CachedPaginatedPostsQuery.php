<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultPaginated;
use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

#[CacheStore('array')]
class CachedPaginatedPostsQuery extends CachedQuery implements WithPaginatedResult
{
    use QueryResultPaginated;

    protected string $model = Post::class;

    public function __construct()
    {
        $this->setCacheFor(120)->setCacheTags('posts');
    }
}
