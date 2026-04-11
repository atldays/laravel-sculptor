<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Tests\Fixtures\Queries\PlainCacheConsumer;
use Atldays\Sculptor\Tests\TestCase;
use DateTimeInterface;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Mockery;

class HasCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::clearResolvedInstances();

        parent::tearDown();
    }

    public function test_it_builds_base_tag_from_class_name(): void
    {
        $this->assertSame('plain_cache_consumer', PlainCacheConsumer::cacheBaseTag());
    }

    public function test_it_returns_datetime_for_numeric_cache_duration(): void
    {
        $query = new PlainCacheConsumer;

        $this->assertInstanceOf(DateTimeInterface::class, $query->cacheFor());

        $query->setCacheFor(30);

        $this->assertInstanceOf(DateTimeInterface::class, $query->cacheFor());
    }

    public function test_it_merges_custom_tags_with_base_tag(): void
    {
        $query = (new PlainCacheConsumer)->setCacheTags('posts')->mergeCacheTags('featured');

        $this->assertSame(['posts', 'featured', 'plain_cache_consumer'], $query->cacheTags());
    }

    public function test_it_flushes_base_cache_via_resolved_store(): void
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('tags')->once()->with('plain_cache_consumer')->andReturnSelf();
        $repository->shouldReceive('flush')->once()->andReturn(true);

        Cache::shouldReceive('store')->once()->with('array')->andReturn($repository);

        $this->assertTrue(PlainCacheConsumer::flushBaseCache());
    }
}
