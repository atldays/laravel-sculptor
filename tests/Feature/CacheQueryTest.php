<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\QueryCache\Query\Builder as CacheQueryBuilder;
use Atldays\Sculptor\Tests\Fixtures\Models\Author;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;
use Atldays\Sculptor\Tests\Fixtures\Queries\CachedPostsQuery;
use Atldays\Sculptor\Tests\TestCase;
use DateTimeInterface;
use Illuminate\Cache\ArrayStore;

class CacheQueryTest extends TestCase
{
    public function test_query_applies_cache_configuration_to_base_builder(): void
    {
        $builder = CachedPostsQuery::make()->query()->getQuery();

        $this->assertInstanceOf(CacheQueryBuilder::class, $builder);
        $this->assertInstanceOf(ArrayStore::class, $builder->getCacheDriver()->getStore());
        $this->assertSame(['posts', 'published', 'cached_posts_query'], $builder->getCacheTags());
        $this->assertInstanceOf(DateTimeInterface::class, $builder->getCacheFor());
    }

    public function test_query_applies_cache_configuration_to_eager_loaded_relations(): void
    {
        $query = CachedPostsQuery::make()->query();
        $eagerLoads = $query->getEagerLoads();

        $this->assertArrayHasKey('author', $eagerLoads);

        $relationQuery = Author::query();
        $eagerLoads['author']($relationQuery);

        /** @var CacheQueryBuilder $relationBuilder */
        $relationBuilder = $relationQuery->getQuery();

        $this->assertInstanceOf(ArrayStore::class, $relationBuilder->getCacheDriver()->getStore());
        $this->assertSame(['posts', 'published', 'cached_posts_query'], $relationBuilder->getCacheTags());
        $this->assertInstanceOf(DateTimeInterface::class, $relationBuilder->getCacheFor());
    }

    public function test_result_returns_only_filtered_models(): void
    {
        $author = Author::create(['name' => 'Jane']);

        Post::create(['author_id' => $author->id, 'title' => 'Visible', 'published' => true]);
        Post::create(['author_id' => $author->id, 'title' => 'Hidden', 'published' => false]);

        $result = CachedPostsQuery::result();

        $this->assertCount(1, $result);
        $this->assertSame('Visible', $result->sole()->title);
        $this->assertTrue($result->sole()->relationLoaded('author'));
    }
}
