<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Attributes\CacheFor;
use Atldays\Sculptor\Tests\Fixtures\Queries\{AttributeTtlPostsQuery, DefaultCacheConsumer, ScopedCachedPostsQuery};
use Atldays\Sculptor\Tests\TestCase;
use DateTime;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class CacheForTest extends TestCase
{
    public static function durations(): array
    {
        return [[600], [0], [-1]];
    }

    #[DataProvider('durations')]
    public function test_the_attribute_preserves_its_seconds(int $seconds): void
    {
        $this->assertSame($seconds, (new CacheFor($seconds))->seconds());
    }

    public function test_the_default_remains_one_hour_without_an_attribute_or_override(): void
    {
        $this->assertDuration(new DefaultCacheConsumer, 3600);
    }

    public function test_class_attributes_are_inherited_and_child_attributes_take_precedence(): void
    {
        $this->assertDuration(new AttributeTtlPostsQuery, 600);
        $this->assertDuration(new class() extends AttributeTtlPostsQuery {}, 600);
        $this->assertDuration(new #[CacheFor(120)] class extends AttributeTtlPostsQuery {}, 120);
    }

    public function test_an_explicit_runtime_duration_including_the_default_overrides_the_attribute(): void
    {
        $first = new AttributeTtlPostsQuery;
        $this->assertDuration($first->setCacheFor(30), 30);
        $this->assertDuration($first->setCacheFor(3600), 3600);
        $this->assertDuration(new AttributeTtlPostsQuery, 600);
    }

    public function test_duration_set_in_a_constructor_overrides_the_class_attribute(): void
    {
        $query = new #[CacheFor(600)] class(42) extends ScopedCachedPostsQuery {};
        $this->assertDuration($query, 120);
    }

    public function test_runtime_datetime_keeps_its_identity_and_survives_explicit_bypass(): void
    {
        $expiration = new DateTime('+10 minutes');
        $query = (new AttributeTtlPostsQuery)->setCacheFor($expiration);
        $this->assertSame($expiration, $query->cacheFor());
        $this->assertSame(0, $query->withoutCache()->cacheFor());
        $this->assertSame($expiration, $query->withoutCache(false)->cacheFor());
    }

    public function test_bypass_has_priority_and_restores_the_attribute_or_latest_runtime_override(): void
    {
        $query = new AttributeTtlPostsQuery;
        $this->assertSame(0, $query->withoutCache()->cacheFor());
        $this->assertDuration($query->withoutCache(false), 600);

        $query->withoutCache()->setCacheFor(30);
        $this->assertSame(0, $query->cacheFor());
        $this->assertDuration($query->withoutCache(false), 30);
    }

    public function test_non_positive_attributes_disable_cache_and_allow_a_positive_runtime_override(): void
    {
        foreach ([
            [new #[CacheFor(0)] class extends AttributeTtlPostsQuery {}, 0],
            [new #[CacheFor(-1)] class extends AttributeTtlPostsQuery {}, -1],
        ] as [$query, $duration]) {
            $this->assertSame($duration, $query->cacheFor());
            $this->assertSame(0, $query->withoutCache()->cacheFor());
            $this->assertSame($duration, $query->withoutCache(false)->cacheFor());
            $this->assertDuration($query->setCacheFor(20), 20);
        }
    }

    public function test_non_positive_runtime_values_override_a_positive_attribute(): void
    {
        $query = new AttributeTtlPostsQuery;
        $this->assertSame(0, $query->setCacheFor(0)->cacheFor());
        $this->assertSame(-1, $query->setCacheFor(-1)->cacheFor());
    }

    public function test_reading_attribute_ttl_executes_no_sql(): void
    {
        DB::enableQueryLog();
        $this->assertDuration(new AttributeTtlPostsQuery, 600);
        $this->assertCount(0, DB::getQueryLog());
    }

    private function assertDuration(object $query, int $seconds): void
    {
        $before = time();
        $expiration = $query->cacheFor();
        $after = time();
        $this->assertInstanceOf(DateTime::class, $expiration);
        $this->assertGreaterThanOrEqual($before + $seconds, $expiration->getTimestamp());
        $this->assertLessThanOrEqual($after + $seconds, $expiration->getTimestamp());
    }
}
