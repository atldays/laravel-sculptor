<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Contracts\WithCache;
use Atldays\Sculptor\Tests\Fixtures\Queries\CachedPostsQuery;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Mockery;

class FlushCacheCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::clearResolvedInstances();

        parent::tearDown();
    }

    public function test_command_flushes_base_cache_for_cacheable_class(): void
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('tags')->once()->with('cached_posts_query')->andReturnSelf();
        $repository->shouldReceive('flush')->once()->andReturn(true);

        Cache::shouldReceive('store')->once()->with('array')->andReturn($repository);

        Artisan::call('sculptor:flush-cache', ['class' => CachedPostsQuery::class]);

        $this->assertStringContainsString(
            sprintf('Cache flushed successfully for class "%s"', CachedPostsQuery::class),
            Artisan::output()
        );
    }

    public function test_command_reports_error_for_non_cacheable_class(): void
    {
        Artisan::call('sculptor:flush-cache', ['class' => self::class]);

        $this->assertStringContainsString(
            sprintf('Expected an implementation of "%s"', WithCache::class),
            Artisan::output()
        );
    }
}
