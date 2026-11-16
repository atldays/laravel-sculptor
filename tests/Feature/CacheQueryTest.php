<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\QueryCache\Query\Builder as CacheQueryBuilder;
use Atldays\Sculptor\Tests\Fixtures\Models\Author;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;
use Atldays\Sculptor\Tests\Fixtures\Queries\BrokenBuilderCachedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\CachedBasePostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\CachedPaginatedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\CachedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\CachedPostsWithPlainAuthorQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\GenericCachedFirstPostQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\GenericCachedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\InspectableCachedPostsQuery;
use Atldays\Sculptor\Tests\TestCase;
use DateTimeInterface;
use Illuminate\Cache\ArrayStore;
use Illuminate\Pagination\PaginationState;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Webmozart\Assert\InvalidArgumentException;

class CacheQueryTest extends TestCase
{
    public function test_query_applies_cache_configuration_to_base_builder(): void
    {
        $builder = CachedPostsQuery::make()->query()->getQuery();

        $this->assertInstanceOf(CacheQueryBuilder::class, $builder);
        $this->assertInstanceOf(ArrayStore::class, $builder->getCacheDriver()->getStore());
        $this->assertSame(['posts', 'published', CachedPostsQuery::cacheBaseTag()], $builder->getCacheTags());
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
        $this->assertSame(['posts', 'published', CachedPostsQuery::cacheBaseTag()], $relationBuilder->getCacheTags());
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
        $this->assertSame(['posts', CachedBasePostsQuery::cacheBaseTag()], $builder->getCacheTags());
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

    /**
     * @return array<string, array{int}>
     */
    public static function nonPositiveCacheDurations(): array
    {
        return ['zero' => [0], 'negative' => [-1]];
    }

    #[DataProvider('nonPositiveCacheDurations')]
    public function test_non_positive_duration_bypasses_result_cache_reads_and_writes(int $duration): void
    {
        $post = Post::create(['title' => 'Original', 'published' => true]);
        $query = (new GenericCachedPostsQuery)->withoutRelations();

        $this->assertSame('Original', $query->effect()->sole()->title);

        DB::table('posts')->where('id', $post->id)->update(['title' => 'Updated']);

        $query->setCacheFor($duration);

        $this->assertSame('Updated', $query->effect()->sole()->title);

        DB::table('posts')->where('id', $post->id)->update(['title' => 'Latest']);

        $this->assertSame('Latest', $query->effect()->sole()->title);
        $this->assertSame('Original', $query->setCacheFor(120)->effect()->sole()->title);
    }

    #[DataProvider('nonPositiveCacheDurations')]
    public function test_non_positive_duration_bypasses_builder_and_relation_cache_reads_and_writes(int $duration): void
    {
        $author = Author::create(['name' => 'Original author']);
        $post = Post::create(['author_id' => $author->id, 'title' => 'Original', 'published' => true]);
        $query = new CachedPostsQuery;

        $cached = $query->effect()->sole();

        $this->assertSame('Original', $cached->title);
        $this->assertSame('Original author', $cached->author->name);

        DB::table('posts')->where('id', $post->id)->update(['title' => 'Updated']);
        DB::table('authors')->where('id', $author->id)->update(['name' => 'Updated author']);

        $fresh = $query->setCacheFor($duration)->effect()->sole();

        $this->assertSame('Updated', $fresh->title);
        $this->assertSame('Updated author', $fresh->author->name);

        DB::table('posts')->where('id', $post->id)->update(['title' => 'Latest']);
        DB::table('authors')->where('id', $author->id)->update(['name' => 'Latest author']);

        $latest = $query->effect()->sole();

        $this->assertSame('Latest', $latest->title);
        $this->assertSame('Latest author', $latest->author->name);

        $cachedAgain = $query->setCacheFor(120)->effect()->sole();

        $this->assertSame('Original', $cachedAgain->title);
        $this->assertSame('Original author', $cachedAgain->author->name);
    }

    /**
     * @return array<string, array{class-string<GenericCachedPostsQuery|CachedPostsQuery>}>
     */
    public static function cacheQueryClasses(): array
    {
        return [
            'result cache' => [GenericCachedPostsQuery::class],
            'builder cache' => [CachedPostsQuery::class],
        ];
    }

    #[DataProvider('cacheQueryClasses')]
    public function test_without_cache_bypasses_reads_and_writes_for_one_instance(string $queryClass): void
    {
        $author = Author::create(['name' => 'Original author']);
        $post = Post::create(['author_id' => $author->id, 'title' => 'Original', 'published' => true]);
        $query = new $queryClass;

        $cached = $query->effect()->sole();

        $this->assertSame('Original', $cached->title);
        $this->assertSame('Original author', $cached->author->name);

        DB::table('posts')->where('id', $post->id)->update(['title' => 'Updated']);
        DB::table('authors')->where('id', $author->id)->update(['name' => 'Updated author']);

        $fresh = $query->withoutCache()->effect()->sole();

        $this->assertSame('Updated', $fresh->title);
        $this->assertSame('Updated author', $fresh->author->name);

        DB::table('posts')->where('id', $post->id)->update(['title' => 'Latest']);
        DB::table('authors')->where('id', $author->id)->update(['name' => 'Latest author']);

        $latest = $query->setCacheFor(600)->effect()->sole();

        $this->assertSame('Latest', $latest->title);
        $this->assertSame('Latest author', $latest->author->name);

        $otherInstance = (new $queryClass)->effect()->sole();

        $this->assertSame('Original', $otherInstance->title);
        $this->assertSame('Original author', $otherInstance->author->name);

        $cachedAgain = $query->withoutCache(false)->effect()->sole();

        $this->assertSame('Original', $cachedAgain->title);
        $this->assertSame('Original author', $cachedAgain->author->name);
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

    public function test_builder_cached_query_fails_when_relation_builder_is_not_query_cacheable(): void
    {
        $author = Author::create(['name' => 'Jane']);

        Post::create(['author_id' => $author->id, 'title' => 'Visible', 'published' => true]);

        $this->expectException(InvalidArgumentException::class);

        CachedPostsWithPlainAuthorQuery::result();
    }

    public function test_cached_paginated_query_caches_pages_independently(): void
    {
        foreach (range(1, 25) as $index) {
            Post::create(['title' => "Post {$index}", 'published' => true]);
        }

        $firstPage = CachedPaginatedPostsQuery::make()
            ->perPage(10)
            ->page(1)
            ->effect();

        $secondPage = CachedPaginatedPostsQuery::make()
            ->perPage(10)
            ->page(2)
            ->effect();

        Post::query()->delete();

        $cachedFirstPage = CachedPaginatedPostsQuery::make()
            ->perPage(10)
            ->page(1)
            ->effect();

        $cachedSecondPage = CachedPaginatedPostsQuery::make()
            ->perPage(10)
            ->page(2)
            ->effect();

        $this->assertSame(1, $firstPage->firstItem());
        $this->assertSame(11, $secondPage->firstItem());
        $this->assertSame(1, $cachedFirstPage->firstItem());
        $this->assertSame(11, $cachedSecondPage->firstItem());
        $this->assertNotSame($cachedFirstPage->currentPage(), $cachedSecondPage->currentPage());
    }

    public function test_cached_pagination_resolves_each_request_page_without_mutating_query_state(): void
    {
        foreach (range(1, 25) as $index) {
            Post::create(['title' => "Post {$index}", 'published' => true]);
        }

        $query = CachedPaginatedPostsQuery::make()->perPage(10);
        $page = 1;

        Paginator::currentPageResolver(function () use (&$page) {
            return $page;
        });

        try {
            $firstPage = $query->effect();
            $page = 2;
            $secondPage = $query->effect();

            $this->assertSame(1, $firstPage->currentPage());
            $this->assertSame(2, $secondPage->currentPage());
            $this->assertSame(1, $firstPage->items()[0]->id);
            $this->assertSame(11, $secondPage->items()[0]->id);

            DB::table('posts')->delete();

            $page = 1;
            $cachedFirstPage = $query->effect();
            $page = 2;
            $cachedSecondPage = $query->effect();

            $this->assertCount(10, $cachedFirstPage->items());
            $this->assertCount(10, $cachedSecondPage->items());
            $this->assertSame(1, $cachedFirstPage->items()[0]->id);
            $this->assertSame(11, $cachedSecondPage->items()[0]->id);
            $this->assertNull($query->paginationData()->page);
        } finally {
            PaginationState::resolveUsing($this->app);
        }
    }

    public function test_cached_pagination_normalizes_model_default_page_size(): void
    {
        foreach (range(1, 15) as $index) {
            Post::create(['title' => "Post {$index}", 'published' => true]);
        }

        $defaultPage = CachedPaginatedPostsQuery::make()->page(1)->effect();

        $this->assertSame(7, $defaultPage->perPage());

        DB::table('posts')->delete();

        $explicitPage = CachedPaginatedPostsQuery::make()->page(1)->perPage(7)->effect();

        $this->assertSame(15, $explicitPage->total());
        $this->assertCount(7, $explicitPage->items());
        $this->assertSame(1, $explicitPage->items()[0]->id);
    }

    public function test_cached_pagination_resolves_custom_page_name_once_and_honors_explicit_page(): void
    {
        foreach (range(1, 15) as $index) {
            Post::create(['title' => "Post {$index}", 'published' => true]);
        }

        $calls = [];

        Paginator::currentPageResolver(function (string $pageName) use (&$calls) {
            $calls[] = $pageName;

            return $pageName === 'results' ? 2 : 1;
        });

        try {
            $query = CachedPaginatedPostsQuery::make()->pageName('results')->perPage(5);
            $resolvedPage = $query->effect();
            $explicitPage = $query->page(3)->effect();

            $this->assertSame(['results'], $calls);
            $this->assertSame(2, $resolvedPage->currentPage());
            $this->assertSame(6, $resolvedPage->items()[0]->id);
            $this->assertSame(3, $explicitPage->currentPage());
            $this->assertSame(11, $explicitPage->items()[0]->id);
        } finally {
            PaginationState::resolveUsing($this->app);
        }
    }

    public function test_cached_pagination_keeps_links_for_different_paths_separate(): void
    {
        foreach (range(1, 5) as $index) {
            Post::create(['title' => "Post {$index}", 'published' => true]);
        }

        $path = 'https://example.com/posts';
        Paginator::currentPathResolver(function () use (&$path) {
            return $path;
        });

        try {
            $query = CachedPaginatedPostsQuery::make()->page(1)->perPage(2);
            $firstPath = $query->effect();
            $path = 'https://example.com/admin/posts';
            $secondPath = $query->effect();

            $this->assertSame('https://example.com/posts?page=2', $firstPath->nextPageUrl());
            $this->assertSame('https://example.com/admin/posts?page=2', $secondPath->nextPageUrl());

            DB::table('posts')->delete();

            $path = 'https://example.com/posts';
            $cachedFirstPath = $query->effect();
            $path = 'https://example.com/admin/posts';
            $cachedSecondPath = $query->effect();

            $this->assertCount(2, $cachedFirstPath->items());
            $this->assertCount(2, $cachedSecondPath->items());
            $this->assertSame($firstPath->nextPageUrl(), $cachedFirstPath->nextPageUrl());
            $this->assertSame($secondPath->nextPageUrl(), $cachedSecondPath->nextPageUrl());
        } finally {
            PaginationState::resolveUsing($this->app);
        }
    }
}
