<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\QueryCache\Query\Builder as CacheQueryBuilder;
use Atldays\Sculptor\Tests\Fixtures\Models\Author;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;
use Atldays\Sculptor\Tests\Fixtures\Queries\BrokenBuilderCachedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\CachedBasePostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\CachedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\GenericCachedFirstPostQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\GenericCachedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\InspectableCachedPostsQuery;
use Atldays\Sculptor\Tests\TestCase;
use DateTimeInterface;
use Illuminate\Cache\ArrayStore;
use Webmozart\Assert\InvalidArgumentException;

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

    public function test_query_with_cache_can_be_used_without_filters_integration(): void
    {
        $builder = CachedBasePostsQuery::make()->query()->getQuery();

        $this->assertInstanceOf(CacheQueryBuilder::class, $builder);
        $this->assertSame(['posts', 'cached_base_posts_query'], $builder->getCacheTags());
        $this->assertInstanceOf(ArrayStore::class, $builder->getCacheDriver()->getStore());
    }

    public function test_query_with_generic_cache_can_cache_results_without_builder_integration(): void
    {
        $author = Author::create(['name' => 'Jane']);

        Post::create(['author_id' => $author->id, 'title' => 'Visible', 'published' => true]);
        Post::create(['author_id' => $author->id, 'title' => 'Hidden', 'published' => false]);

        $first = GenericCachedPostsQuery::result();

        Post::query()->delete();

        $second = GenericCachedPostsQuery::result();

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame('Visible', $second->sole()->title);
    }

    public function test_cached_query_can_cache_first_result_without_builder_integration(): void
    {
        $post = Post::create(['title' => 'First', 'published' => true]);

        $first = GenericCachedFirstPostQuery::result();

        Post::query()->delete();

        $second = GenericCachedFirstPostQuery::result();

        $this->assertSame($post->id, $first?->id);
        $this->assertSame($post->id, $second?->id);
    }

    public function test_result_cache_can_be_flushed_via_base_tag(): void
    {
        $author = Author::create(['name' => 'Jane']);

        Post::create(['author_id' => $author->id, 'title' => 'Visible', 'published' => true]);

        $first = GenericCachedPostsQuery::result();

        GenericCachedPostsQuery::flushBaseCache();

        Post::query()->delete();
        Post::create(['author_id' => $author->id, 'title' => 'Updated', 'published' => true]);

        $second = GenericCachedPostsQuery::result();

        $this->assertSame('Visible', $first->sole()->title);
        $this->assertSame('Updated', $second->sole()->title);
    }

    public function test_identical_result_cache_queries_share_the_same_key(): void
    {
        $first = new InspectableCachedPostsQuery;
        $second = new InspectableCachedPostsQuery;

        $this->assertSame($first->exposedCacheKey(), $second->exposedCacheKey());
    }

    public function test_result_cache_key_changes_when_query_shape_changes(): void
    {
        $base = new InspectableCachedPostsQuery;
        $limited = (new InspectableCachedPostsQuery)->limit(5);
        $selected = (new InspectableCachedPostsQuery)->withSelect(['posts.id']);
        $withoutRelations = (new InspectableCachedPostsQuery)->withoutRelations();

        $this->assertNotSame($base->exposedCacheKey(), $limited->exposedCacheKey());
        $this->assertNotSame($base->exposedCacheKey(), $selected->exposedCacheKey());
        $this->assertNotSame($base->exposedCacheKey(), $withoutRelations->exposedCacheKey());
    }

    public function test_builder_cached_query_fails_without_query_cacheable_model(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BrokenBuilderCachedPostsQuery::make()->query();
    }
}
