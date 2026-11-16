<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Tests\Fixtures\Queries\Other\PlainCacheConsumer as OtherPlainCacheConsumer;
use Atldays\Sculptor\Tests\Fixtures\Queries\PlainCacheConsumer;
use Atldays\Sculptor\Tests\TestCase;
use DateTime;
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

    public function test_it_builds_base_tag_from_fully_qualified_class_name(): void
    {
        $this->assertSame('sculptor:Atldays\\Sculptor\\Tests\\Fixtures\\Queries\\PlainCacheConsumer', PlainCacheConsumer::cacheBaseTag());
    }

    public function test_it_returns_datetime_for_numeric_cache_duration(): void
    {
        $query = new PlainCacheConsumer;

        $this->assertInstanceOf(DateTimeInterface::class, $query->cacheFor());

        $query->setCacheFor(30);

        $this->assertInstanceOf(DateTimeInterface::class, $query->cacheFor());
    }

    public function test_without_cache_preserves_configured_expiration(): void
    {
        $expiresAt = new DateTime('+5 minutes');
        $query = (new PlainCacheConsumer)->setCacheFor($expiresAt)->withoutCache();

        $this->assertSame(0, $query->cacheFor());
        $this->assertSame($expiresAt, $query->withoutCache(false)->cacheFor());
    }

    public function test_it_merges_custom_tags_with_base_tag(): void
    {
        $query = (new PlainCacheConsumer)->setCacheTags('posts')->mergeCacheTags('featured');

        $this->assertSame(['posts', 'featured', PlainCacheConsumer::cacheBaseTag()], $query->cacheTags());
    }

    public function test_it_flushes_base_cache_via_resolved_store(): void
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('tags')->once()->with(PlainCacheConsumer::cacheBaseTag())->andReturnSelf();
        $repository->shouldReceive('flush')->once()->andReturn(true);

        Cache::shouldReceive('store')->once()->with('array')->andReturn($repository);

        $this->assertTrue(PlainCacheConsumer::flushBaseCache());
    }

    public function test_flushing_base_cache_preserves_class_with_same_name_in_another_namespace(): void
    {
        $first = new PlainCacheConsumer;
        $second = new OtherPlainCacheConsumer;

        $first->getCache()->put('result', 'first', 60);
        $second->getCache()->put('result', 'second', 60);

        $this->assertSame('first', $first->getCache()->get('result'));
        $this->assertSame('second', $second->getCache()->get('result'));

        PlainCacheConsumer::flushBaseCache();

        $this->assertNull($first->getCache()->get('result'));
        $this->assertSame('second', $second->getCache()->get('result'));
    }
}
