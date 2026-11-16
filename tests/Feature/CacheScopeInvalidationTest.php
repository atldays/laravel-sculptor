<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Attributes\CacheStoreFromConfig;
use Atldays\Sculptor\Contracts\WithCacheScopeInvalidation;
use Atldays\Sculptor\Tests\Fixtures\Queries;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Support\Facades\{Cache, DB};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class CacheScopeInvalidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([42, 57] as $owner) {
            DB::table('authors')->insert(['id' => $owner, 'name' => 'Owner ' . $owner]);

            foreach ([false, true] as $published) {
                DB::table('posts')->insert(['author_id' => $owner, 'title' => 'Original', 'published' => $published]);
            }
        }
    }

    public static function strategies(): array
    {
        return [
            'result cache' => [Queries\ScopedCachedPostsQuery::class],
            'builder cache' => [Queries\ScopedBuilderCachedPostsQuery::class],
        ];
    }

    #[DataProvider('strategies')]
    public function test_one_scope_flushes_every_filter_select_and_limit_variant_but_preserves_other_owners(string $class): void
    {
        $queries = [
            new $class(42),
            $class::make(ownerId: 42, published: true),
            $class::make(ownerId: 42, published: false),
            $class::make(ownerId: 42)->limit(1)->withSelect(['posts.id', 'posts.title']),
        ];

        foreach ($queries as $query) {
            $this->assertInstanceOf(WithCacheScopeInvalidation::class, $query);
            $this->assertSame('Original', $query->effect()->first()->title);
        }

        $otherOwner = $class::make(ownerId: 57);
        $otherOwner->effect();
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $constructed = $class::$constructions;
        $this->assertTrue($class::flushScope(ownerId: 42));
        $this->assertSame($constructed, $class::$constructions);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Original', $otherOwner->effect()->first()->title);
        $this->assertCount(0, DB::getQueryLog());

        foreach ($queries as $query) {
            $this->assertSame('Updated', $query->effect()->first()->title);
        }

        $this->assertCount(4, DB::getQueryLog());
    }

    public static function compositeStrategies(): array
    {
        return [
            'result cache' => [Queries\CompositeScopedCachedPostsQuery::class],
            'builder cache' => [Queries\CompositeScopedBuilderCachedPostsQuery::class],
        ];
    }

    public static function paginatedStrategies(): array
    {
        return [
            'result cache' => [Queries\ScopedPaginatedCachedPostsQuery::class],
            'builder cache' => [Queries\ScopedPaginatedBuilderCachedPostsQuery::class],
        ];
    }

    #[DataProvider('paginatedStrategies')]
    public function test_scope_invalidation_covers_all_pages_without_flushing_other_owners(string $class): void
    {
        $first = $class::make(ownerId: 42)->perPage(1)->page(1);
        $second = $class::make(ownerId: 42)->perPage(1)->page(2);
        $other = $class::make(ownerId: 57)->perPage(1)->page(1);
        $first->effect();
        $second->effect();
        $other->effect();
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $class::flushScope(ownerId: 42);

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Original', $other->effect()->items()[0]->title);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Updated', $first->effect()->items()[0]->title);
        $this->assertSame('Updated', $second->effect()->items()[0]->title);
    }

    #[DataProvider('compositeStrategies')]
    public function test_composite_scopes_match_the_whole_tuple_and_resolve_optional_defaults(string $class): void
    {
        $active = $class::make(ownerId: 42, published: true);
        $inactive = $class::make(ownerId: 42, published: false);
        $all = $class::make(ownerId: 42);
        $other = $class::make(ownerId: 57, published: true);

        foreach ([$active, $inactive, $all, $other] as $query) {
            $query->effect();
        }

        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $this->assertTrue($class::flushScope(published: true, ownerId: 42));
        $this->assertCount(0, DB::getQueryLog());

        foreach ([$inactive, $all, $other] as $query) {
            $this->assertSame('Original', $query->effect()->first()->title);
        }

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Updated', $active->effect()->sole()->title);
        $this->assertCount(1, DB::getQueryLog());

        $class::flushScope(ownerId: 42); // Only the default published=null group.
        $this->assertSame('Updated', $all->effect()->first()->title);
        $this->assertSame('Original', $inactive->effect()->sole()->title);
        $this->assertCount(2, DB::getQueryLog());
    }

    #[DataProvider('strategies')]
    public function test_scope_flush_invalidates_entries_tagged_before_the_resolver_refactor(string $class): void
    {
        // Recorded from the static resolver before the object API refactor.
        $legacyTag = $class === Queries\ScopedCachedPostsQuery::class
            ? 'sculptor:scope:f7d05102c1226c5b0e13125d333272b636e6e33369697de160fc2fd2b5d1c8c8'
            : 'sculptor:scope:1dfb9af3cf1bc63baf64acd99e805a7a5a3d702134d9aa1ac48747f33494fb12';
        $store = $class::cache();
        $entry = $store->tags([$class::cacheBaseTag(), $legacyTag]);
        $other = $class::make(ownerId: 57)->getCache();
        $entry->put('legacy', 'preserved until flush', 120);
        $other->put('other', 'preserved', 120);
        $constructions = $class::$constructions;
        DB::enableQueryLog();

        $this->assertTrue($class::flushScope(ownerId: 42));

        $this->assertSame($constructions, $class::$constructions);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertNull($store->tags([$class::cacheBaseTag(), $legacyTag])->get('legacy'));
        $this->assertSame('preserved', $other->get('other'));
    }

    #[DataProvider('strategies')]
    public function test_automatic_scopes_are_class_local_and_manual_tags_still_span_classes(string $class): void
    {
        $otherClass = $class === Queries\ScopedCachedPostsQuery::class ? Queries\ScopedBuilderCachedPostsQuery::class : Queries\ScopedCachedPostsQuery::class;
        $first = $class::make(ownerId: 42);
        $second = $otherClass::make(ownerId: 42);
        $first->effect();
        $second->effect();
        DB::table('posts')->update(['title' => 'Updated']);

        $class::flushScope(ownerId: 42);
        $this->assertSame('Updated', $first->effect()->first()->title);
        $this->assertSame('Original', $second->effect()->first()->title);

        $class::flushCacheTags('projects');
        $this->assertSame('Updated', $second->effect()->first()->title);
    }

    #[DataProvider('strategies')]
    public function test_base_cache_still_invalidates_all_automatic_scopes_of_the_class(string $class): void
    {
        $first = $class::make(ownerId: 42);
        $second = $class::make(ownerId: 57);
        $first->effect();
        $second->effect();
        DB::table('posts')->update(['title' => 'Updated']);

        $class::flushBaseCache();

        $this->assertSame('Updated', $first->effect()->first()->title);
        $this->assertSame('Updated', $second->effect()->first()->title);
    }

    #[DataProvider('strategies')]
    public function test_invalid_scope_input_does_not_access_the_store_or_invalidate_existing_results(string $class): void
    {
        $query = $class::make(ownerId: 42);
        $repository = $query->getCache();
        $repository->put('sentinel', 'preserved', 60);
        Cache::shouldReceive('store')->never();

        foreach ([[], ['ownerId' => '42'], ['ownerId' => 42, 'published' => true]] as $arguments) {
            try {
                $class::flushScope(...$arguments);
                $this->fail('Invalid scope input must fail before cache access.');
            } catch (InvalidArgumentException) {
                $this->assertSame('preserved', $repository->get('sentinel'));
            }
        }
    }

    public function test_inherited_scope_and_store_attributes_work_without_constructing_the_query_on_flush(): void
    {
        config()->set('cache.stores.scopes', ['driver' => 'array']);
        config()->set('services.scope_store', 'scopes');
        $query = new #[CacheStoreFromConfig('services.scope_store')] class(42) extends Queries\ScopedCachedPostsQuery {};
        $class = $query::class;
        $this->assertSame('Original', $query->effect()->first()->title);
        Cache::store('array')->tags('projects')->put('unrelated', 'preserved', 60);
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $this->assertTrue($class::flushScope(ownerId: 42));
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('preserved', Cache::store('array')->tags('projects')->get('unrelated'));
        $this->assertSame('Updated', $query->effect()->first()->title);
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_builder_cached_eager_loads_share_the_automatic_scope_tag(): void
    {
        $query = Queries\ScopedBuilderCachedPostsQuery::make(ownerId: 42)->withRelations('author');
        $this->assertSame('Owner 42', $query->effect()->first()->author->name);
        DB::table('authors')->where('id', 42)->update(['name' => 'Updated owner']);

        Queries\ScopedBuilderCachedPostsQuery::flushScope(ownerId: 42);

        $this->assertSame('Updated owner', $query->effect()->first()->author->name);
    }

    #[DataProvider('strategies')]
    public function test_explicit_bypass_preserves_existing_scoped_entries(string $class): void
    {
        $query = $class::make(ownerId: 42);
        $query->effect();
        DB::table('posts')->update(['title' => 'Updated']);

        $this->assertSame('Updated', $query->withoutCache()->effect()->first()->title);
        $this->assertSame('Original', $query->withoutCache(false)->effect()->first()->title);
    }
}
