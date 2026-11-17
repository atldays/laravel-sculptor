<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Contracts\WithCacheTagInvalidation;
use Atldays\Sculptor\Tests\Fixtures\Queries\{OwnerBuilderCachedPostsQuery, OwnerCachedPostsQuery};
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class CacheTagInvalidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([42, 57, 73] as $id) {
            DB::table('authors')->insert(['id' => $id, 'name' => 'Owner ' . $id]);
            DB::table('posts')->insert(['author_id' => $id, 'title' => 'Original ' . $id]);
        }
    }

    public static function strategies(): array
    {
        return [
            'result cache' => [OwnerCachedPostsQuery::class],
            'builder cache' => [OwnerBuilderCachedPostsQuery::class],
        ];
    }

    #[DataProvider('strategies')]
    public function test_one_owner_can_be_invalidated_without_flushing_the_shared_base_tag(string $class): void
    {
        $first = $class::make(ownerId: 42);
        $second = $class::make(ownerId: 57);
        $this->assertInstanceOf(WithCacheTagInvalidation::class, $first);
        $this->assertSame('Original 42', $first->effect()->sole()->title);
        $this->assertSame('Original 57', $second->effect()->sole()->title);

        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $this->assertTrue($class::flushCacheTags('projects:user:42'));
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Original 57', $second->effect()->sole()->title);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Updated', $first->effect()->sole()->title);
        $this->assertSame('Original 57', $second->effect()->sole()->title);
        $this->assertCount(1, DB::getQueryLog());
    }

    #[DataProvider('strategies')]
    public function test_multiple_tags_invalidate_the_union_and_preserve_unrelated_owners(string $class): void
    {
        $queries = [];

        foreach ([42, 57, 73] as $owner) {
            $queries[$owner] = $class::make(ownerId: $owner);
            $this->assertSame('Original ' . $owner, $queries[$owner]->effect()->sole()->title);
        }

        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $this->assertTrue($class::flushCacheTags('projects:user:42', 'projects:user:57'));
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Original 73', $queries[73]->effect()->sole()->title);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Updated', $queries[42]->effect()->sole()->title);
        $this->assertSame('Updated', $queries[57]->effect()->sole()->title);
        $this->assertCount(2, DB::getQueryLog());
    }

    #[DataProvider('strategies')]
    public function test_a_shared_tag_invalidates_both_cache_strategies_across_query_classes(string $invalidator): void
    {
        $first = OwnerCachedPostsQuery::make(ownerId: 42);
        $second = OwnerBuilderCachedPostsQuery::make(ownerId: 42);
        $unrelated = OwnerBuilderCachedPostsQuery::make(ownerId: 57);
        $this->assertSame('Original 42', $first->effect()->sole()->title);
        $this->assertSame('Original 42', $second->effect()->sole()->title);
        $this->assertSame('Original 57', $unrelated->effect()->sole()->title);
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $invalidator::flushCacheTags('projects:user:42');

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Original 57', $unrelated->effect()->sole()->title);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Updated', $first->effect()->sole()->title);
        $this->assertSame('Updated', $second->effect()->sole()->title);
        $this->assertCount(2, DB::getQueryLog());
    }

    #[DataProvider('strategies')]
    public function test_base_invalidation_still_flushes_all_owners_of_only_that_query_class(string $class): void
    {
        $first = $class::make(ownerId: 42);
        $second = $class::make(ownerId: 57);
        $otherClass = $class === OwnerCachedPostsQuery::class ? OwnerBuilderCachedPostsQuery::class : OwnerCachedPostsQuery::class;
        $other = $otherClass::make(ownerId: 42);
        $first->effect();
        $second->effect();
        $other->effect();
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $this->assertTrue($class::flushBaseCache());
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Original 42', $other->effect()->sole()->title);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Updated', $first->effect()->sole()->title);
        $this->assertSame('Updated', $second->effect()->sole()->title);
        $this->assertCount(2, DB::getQueryLog());
    }

    #[DataProvider('strategies')]
    public function test_static_invalidation_needs_neither_constructor_arguments_nor_sql(string $class): void
    {
        // Both fixtures have a required owner argument; invalidation must not construct them.
        DB::enableQueryLog();

        $this->assertTrue($class::flushCacheTags('projects:user:42'));
        $this->assertCount(0, DB::getQueryLog());
    }
}
