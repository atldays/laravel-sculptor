<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCollection;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

#[CacheStore('array')]
class CachedBasePostsQuery extends BuilderCachedQuery
{
    use QueryResultCollection;

    protected string $model = Post::class;

    protected array $with = ['author'];

    public function __construct()
    {
        $this
            ->limit(10)
            ->setCacheFor(120)
            ->setCacheTags('posts');
    }
}
