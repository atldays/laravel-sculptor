<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Attributes\CacheFor;
use Atldays\Sculptor\Tests\Fixtures\Queries\{AttributeTtlBuilderPostsQuery, AttributeTtlPostsQuery};
use Atldays\Sculptor\Tests\TestCase;
use DateTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class CacheForAttributeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('authors')->insert(['id' => 42, 'name' => 'Owner']);
        DB::table('posts')->insert(['author_id' => 42, 'title' => 'Original']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function strategies(): array
    {
        return [
            'result cache' => [AttributeTtlPostsQuery::class],
            'builder cache' => [AttributeTtlBuilderPostsQuery::class],
        ];
    }

    #[DataProvider('strategies')]
    public function test_attribute_duration_controls_real_expiration_and_new_query_instances_share_entries(string $class): void
    {
        $now = Carbon::now();
        $this->assertSame('Original', $class::make()->effect()->sole()->title);
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        Carbon::setTestNow($now->copy()->addSeconds(300));
        $this->assertSame('Original', $class::make()->effect()->sole()->title);
        $this->assertCount(0, DB::getQueryLog());

        Carbon::setTestNow($now->copy()->addSeconds(601));
        $this->assertSame('Updated', $class::make()->effect()->sole()->title);
        $this->assertCount(1, DB::getQueryLog());
    }

    public static function runtimeExpirations(): iterable
    {
        foreach (self::strategies() as $name => [$class]) {
            yield $name . ' seconds' => [$class, false];
            yield $name . ' DateTime' => [$class, true];
        }
    }

    #[DataProvider('runtimeExpirations')]
    public function test_runtime_duration_controls_expiration_instead_of_the_attribute(string $class, bool $absolute): void
    {
        $now = Carbon::now();
        $query = $class::make()->setCacheFor($absolute ? new DateTime('+2 seconds') : 2);
        $this->assertSame('Original', $query->effect()->sole()->title);
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        Carbon::setTestNow($now->copy()->addSeconds(3));

        $this->assertSame('Updated', $query->effect()->sole()->title);
        $this->assertCount(1, DB::getQueryLog());
    }

    #[DataProvider('strategies')]
    public function test_zero_attribute_bypasses_reads_and_writes_while_preserving_entries_created_with_runtime_ttl(string $parent): void
    {
        $query = $parent === AttributeTtlPostsQuery::class
            ? new #[CacheFor(0)] class extends AttributeTtlPostsQuery {}
        : new #[CacheFor(0)] class extends AttributeTtlBuilderPostsQuery {};
        $class = $query::class;
        $this->assertSame('Original', $class::make()->setCacheFor(600)->effect()->sole()->title);
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $this->assertSame('Updated', $query->effect()->sole()->title);
        $this->assertSame('Updated', $class::make()->effect()->sole()->title);
        $this->assertCount(2, DB::getQueryLog());
        $this->assertSame('Original', $query->setCacheFor(600)->effect()->sole()->title);
        $this->assertCount(2, DB::getQueryLog());
    }

    #[DataProvider('strategies')]
    public function test_explicit_bypass_stays_active_after_setting_runtime_ttl_and_can_be_reversed(string $class): void
    {
        $query = $class::make();
        $query->effect();
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $this->assertSame('Updated', $query->withoutCache()->setCacheFor(30)->effect()->sole()->title);
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame('Original', $query->withoutCache(false)->effect()->sole()->title);
        $this->assertCount(1, DB::getQueryLog());
    }

    #[DataProvider('strategies')]
    public function test_scope_invalidation_continues_to_work_with_attribute_only_ttl(string $class): void
    {
        $query = $class::make();
        $query->effect();
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();

        $this->assertTrue($class::flushCacheByScope(ownerId: 42));
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('Updated', $query->effect()->sole()->title);
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_attribute_ttl_is_applied_to_builder_cached_relations(): void
    {
        $query = AttributeTtlBuilderPostsQuery::make()->withRelations('author');
        $this->assertSame('Owner', $query->effect()->sole()->author->name);
        DB::table('authors')->where('id', 42)->update(['name' => 'Updated owner']);
        $now = Carbon::now();

        Carbon::setTestNow($now->copy()->addSeconds(300));
        $this->assertSame('Owner', $query->effect()->sole()->author->name);

        Carbon::setTestNow($now->copy()->addSeconds(601));
        $this->assertSame('Updated owner', $query->effect()->sole()->author->name);
    }
}
