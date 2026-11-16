<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Attributes\CacheStoreFromConfig;
use Atldays\Sculptor\Contracts\WithCacheScopeInvalidation;
use Atldays\Sculptor\Support\CacheScopeResolver;
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
    public function test_full_tuple_flush_still_invalidates_legacy_entries_without_subset_tags(string $class): void
    {
        $query = $class::make(ownerId: 42, published: true, tenantId: 9);
        $tag = CacheScopeResolver::make($query)->tag();
        $store = $class::cache();
        $store->tags([$class::cacheBaseTag(), $tag])->put('legacy', 'old entry', 120);
        $other = $class::make(ownerId: 57, published: true, tenantId: 9)->getCache();
        $other->put('other', 'preserved', 120);
        DB::enableQueryLog();

        $this->assertTrue($class::flushScope(tenantId: 9, published: true, ownerId: 42));

        $this->assertCount(0, DB::getQueryLog());
        $this->assertNull($store->tags([$class::cacheBaseTag(), $tag])->get('legacy'));
        $this->assertSame('preserved', $other->get('other'));
    }

    #[DataProvider('compositeStrategies')]
    public function test_partial_scopes_match_multiple_filters_together_across_remaining_parameters(string $class): void
    {
        $targets = [];
        $preserved = [];

        foreach ([7, 9] as $tenantId) {
            $targets[] = $class::make(ownerId: 42, published: true, tenantId: $tenantId);
            $preserved[] = $class::make(ownerId: 42, published: false, tenantId: $tenantId);
            $preserved[] = $class::make(ownerId: 57, published: true, tenantId: $tenantId);
        }

        foreach ([...$targets, ...$preserved] as $query) {
            $this->assertSame('Original', $query->effect()->first()->title);
        }

        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();
        $this->assertTrue($class::flushScope(published: true, ownerId: 42));
        $this->assertCount(0, DB::getQueryLog());

        foreach ($preserved as $query) {
            $this->assertSame('Original', $query->effect()->first()->title);
        }

        $this->assertCount(0, DB::getQueryLog());

        foreach ($targets as $query) {
            $this->assertSame('Updated', $query->effect()->first()->title);
        }
    }

    #[DataProvider('compositeStrategies')]
    public function test_a_later_scope_parameter_can_be_flushed_without_supplying_earlier_parameters(string $class): void
    {
        $targets = [$class::make(ownerId: 42, published: true), $class::make(ownerId: 57, published: true)];
        $preserved = [$class::make(ownerId: 42, published: false), $class::make(ownerId: 57), $class::make(ownerId: 42, published: false, tenantId: 9)];

        foreach ([...$targets, ...$preserved] as $query) {
            $query->effect();
        }

        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();
        $this->assertTrue($class::flushScope(published: true));
        $this->assertCount(0, DB::getQueryLog());

        foreach ($preserved as $query) {
            $this->assertSame('Original', $query->effect()->first()->title);
        }

        $this->assertCount(0, DB::getQueryLog());

        foreach ($targets as $query) {
            $this->assertSame('Updated', $query->effect()->first()->title);
        }
    }

    #[DataProvider('compositeStrategies')]
    public function test_explicit_null_matches_only_null_variants_of_the_supplied_owner(string $class): void
    {
        $target = $class::make(ownerId: 42);
        $preserved = [$class::make(ownerId: 42, published: true), $class::make(ownerId: 42, published: false), $class::make(ownerId: 57)];

        foreach ([$target, ...$preserved] as $query) {
            $query->effect();
        }

        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();
        $this->assertTrue($class::flushScope(ownerId: 42, published: null));
        $this->assertCount(0, DB::getQueryLog());

        foreach ($preserved as $query) {
            $this->assertSame('Original', $query->effect()->first()->title);
        }

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Updated', $target->effect()->first()->title);
    }

    #[DataProvider('paginatedStrategies')]
    public function test_partial_scope_flush_covers_all_statuses_pages_page_sizes_and_totals(string $class): void
    {
        foreach ([42, 57] as $ownerId) {
            foreach ([false, true] as $published) {
                for ($i = 0; $i < 2; $i++) {
                    DB::table('posts')->insert(['author_id' => $ownerId, 'title' => 'Original', 'published' => $published]);
                }
            }
        }

        $targets = [];
        $preserved = [];

        foreach ([null, false, true] as $published) {
            foreach ([1, 2, 3] as $page) {
                $targets[] = [$class::make(ownerId: 42, published: $published)->perPage(1)->page($page), $published];
                $preserved[] = $class::make(ownerId: 57, published: $published)->perPage(1)->page($page);
            }
        }

        $targets[] = [$class::make(ownerId: 42, published: true)->perPage(2)->page(2), true];

        foreach ($targets as [$query]) {
            $this->assertSame('Original', $query->effect()->items()[0]->title);
        }

        foreach ($preserved as $query) {
            $query->effect();
        }

        DB::table('posts')->update(['title' => 'Updated']);
        DB::table('posts')->insert(['author_id' => 42, 'title' => 'Updated', 'published' => true]);
        DB::enableQueryLog();
        $constructions = $class::$constructions;
        $this->assertTrue($class::flushScope(ownerId: 42));
        $this->assertSame($constructions, $class::$constructions);
        $this->assertCount(0, DB::getQueryLog());

        foreach ($preserved as $query) {
            $this->assertSame('Original', $query->effect()->items()[0]->title);
        }

        $this->assertCount(0, DB::getQueryLog());

        foreach ($targets as [$query, $published]) {
            $result = $query->effect();
            $this->assertSame('Updated', $result->items()[0]->title);
            $this->assertSame(match ($published) {
                null => 7,
                true => 4,
                false => 3,
            }, $result->total());
        }
    }

    #[DataProvider('compositeStrategies')]
    public function test_invalid_partial_filters_are_all_checked_before_accessing_the_store(string $class): void
    {
        $repository = $class::make(ownerId: 42)->getCache();
        $repository->put('sentinel', 'preserved', 60);
        Cache::shouldReceive('store')->never();

        foreach ([[], ['ownerId' => '42'], ['ownerId' => 42, 'published' => 'true'], ['unknown' => 42], [42, 'ownerId' => 57]] as $arguments) {
            try {
                $class::flushScope(...$arguments);
                $this->fail('Invalid partial input must fail before cache access.');
            } catch (InvalidArgumentException) {
                $this->assertSame('preserved', $repository->get('sentinel'));
            }
        }
    }

    #[DataProvider('compositeStrategies')]
    public function test_composite_scopes_match_all_supplied_values_and_omit_defaults_when_flushing(string $class): void
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

        $class::flushScope(ownerId: 42); // Every published value, including null.
        $this->assertSame('Updated', $all->effect()->first()->title);
        $this->assertSame('Updated', $inactive->effect()->sole()->title);
        $this->assertSame('Original', $other->effect()->sole()->title);
        $this->assertCount(3, DB::getQueryLog());
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
        $query = new #[CacheStoreFromConfig('services.scope_store')] class(42, true) extends Queries\CompositeScopedCachedPostsQuery {};
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

    #[DataProvider('compositeStrategies')]
    public function test_eager_loads_are_invalidated_by_partial_scope_filters(string $class): void
    {
        $query = $class::make(ownerId: 42, published: true)->withRelations('author');
        $this->assertSame('Owner 42', $query->effect()->first()->author->name);
        DB::table('authors')->where('id', 42)->update(['name' => 'Updated owner']);

        $class::flushScope(ownerId: 42);

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
