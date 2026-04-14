<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCollection;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;

#[CacheStore('array')]
class InspectableCachedPostsQuery extends CachedQuery
{
    use QueryResultCollection;

    protected string $model = Post::class;

    protected array $with = ['author'];

    public function __construct()
    {
        $this->limit(10)->setCacheFor(120)->setCacheTags('posts');
    }

    public function exposedCacheKey(string $kind = 'collection'): string
    {
        return $this->cacheKey($kind);
    }
}
