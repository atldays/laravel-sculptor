<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Tests\Fixtures\Data\{FailingOwnerScopeData, OwnerScopeData};
use Atldays\Sculptor\Tests\Fixtures\Models\Author;
use Atldays\Sculptor\Tests\Fixtures\Queries;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Support\Facades\{Cache, DB};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class CacheScopeObjectInvalidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([42, 57] as $id) {
            DB::table('authors')->insert(['id' => $id, 'name' => 'Owner ' . $id]);

            foreach ([false, true] as $published) {
                DB::table('posts')->insert(['author_id' => $id, 'title' => 'Original', 'published' => $published]);
            }
        }
    }

    public static function strategies(): array
    {
        return [
            'model result cache' => [Queries\ModelScopedPostsQuery::class, 'model'],
            'model builder cache' => [Queries\ModelScopedBuilderPostsQuery::class, 'model'],
            'data result cache' => [Queries\DataScopedPostsQuery::class, 'data'],
            'data builder cache' => [Queries\DataScopedBuilderPostsQuery::class, 'data'],
        ];
    }

    public static function modelStrategies(): array
    {
        return [
            'result cache' => [Queries\ModelScopedPostsQuery::class],
            'builder cache' => [Queries\ModelScopedBuilderPostsQuery::class],
        ];
    }

    #[DataProvider('strategies')]
    public function test_new_object_instances_invalidate_all_variants_without_affecting_other_owners(string $class, string $kind): void
    {
        $owner = $this->owner($kind, 42);
        $queries = [
            new $class($owner),
            $class::make(owner: $owner, published: true),
            $class::make(owner: $owner, published: false),
        ];

        foreach ($queries as $query) {
            $this->assertSame('Original', $query->effect()->first()->title);
        }

        $other = $class::make(owner: $this->owner($kind, 57));
        $other->effect();
        $freshOwner = $this->owner($kind, 42);

        if ($freshOwner instanceof Author) {
            $freshOwner->name = 'Dirty name';
            $freshOwner->setRelation('posts', collect());
        }

        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();
        $constructions = $class::$constructions;

        $this->assertTrue($class::flushScope(owner: $freshOwner));

        $this->assertSame($constructions, $class::$constructions);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Original', $other->effect()->first()->title);
        $this->assertCount(0, DB::getQueryLog());

        foreach ($queries as $query) {
            $this->assertSame('Updated', $query->effect()->first()->title);
        }

        $this->assertCount(3, DB::getQueryLog());
    }

    #[DataProvider('modelStrategies')]
    public function test_deleted_models_retain_the_identity_used_to_write_cache_entries(string $class): void
    {
        $owner = Author::findOrFail(42);
        $query = $class::make(owner: $owner);
        $this->assertCount(2, $query->effect());
        DB::table('posts')->where('author_id', 42)->delete();
        $owner->delete();
        $this->assertFalse($owner->exists);
        DB::enableQueryLog();

        $this->assertTrue($class::flushScope(owner: $owner));
        $this->assertCount(0, DB::getQueryLog());
        $this->assertCount(0, $query->effect());
        $this->assertCount(1, DB::getQueryLog());
    }

    #[DataProvider('modelStrategies')]
    public function test_keyed_model_references_need_no_database_lookup_during_invalidation(string $class): void
    {
        $query = $class::make(owner: Author::findOrFail(42));
        $query->effect();
        DB::table('posts')->update(['title' => 'Updated']);
        $reference = new Author(['id' => 42]);
        $this->assertFalse($reference->exists);
        DB::enableQueryLog();

        $class::flushScope(owner: $reference);

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Updated', $query->effect()->first()->title);
        $this->assertCount(1, DB::getQueryLog());
    }

    #[DataProvider('modelStrategies')]
    public function test_models_with_identical_keys_and_different_connection_names_have_separate_entries(string $class): void
    {
        $first = Author::findOrFail(42);
        $second = (clone $first)->setConnection('tenant_b');
        $firstQuery = $class::make(owner: $first);
        $secondQuery = $class::make(owner: $second);
        $firstQuery->effect();
        $secondQuery->effect();
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $class::flushScope(owner: $first);

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Original', $secondQuery->effect()->first()->title);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Updated', $firstQuery->effect()->first()->title);
        $this->assertCount(1, DB::getQueryLog());
    }

    #[DataProvider('modelStrategies')]
    public function test_models_missing_their_key_fail_before_cache_access(string $class): void
    {
        $partial = Author::query()->select('name')->firstOrFail();
        $this->assertTrue($partial->exists);
        Cache::shouldReceive('store')->never();
        $this->expectException(InvalidArgumentException::class);

        $class::flushScope(owner: $partial);
    }

    public function test_custom_key_failures_abort_before_cache_access(): void
    {
        $value = new FailingOwnerScopeData(42);
        Cache::shouldReceive('store')->never();
        $this->expectException(RuntimeException::class);

        Queries\DataScopedPostsQuery::flushScope(owner: $value);
    }

    #[DataProvider('strategies')]
    public function test_class_and_manual_tag_invalidation_keep_their_existing_meaning(string $class, string $kind): void
    {
        $first = $class::make(owner: $this->owner($kind, 42));
        $second = $class::make(owner: $this->owner($kind, 57));
        $first->effect();
        $second->effect();
        DB::table('posts')->update(['title' => 'Updated']);

        $class::flushBaseCache();

        $this->assertSame('Updated', $first->effect()->first()->title);
        $this->assertSame('Updated', $second->effect()->first()->title);
        DB::table('posts')->update(['title' => 'Latest']);
        $class::flushCacheTags('projects');
        $this->assertSame('Latest', $first->effect()->first()->title);
        $this->assertSame('Latest', $second->effect()->first()->title);
    }

    private function owner(string $kind, int $id): Author|OwnerScopeData
    {
        return $kind === 'model' ? Author::findOrFail($id) : new OwnerScopeData($id);
    }
}
