<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Contracts\WithCache;
use Atldays\Sculptor\Tests\Fixtures\Queries\CachedPostsQuery;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Cache\Repository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, Cache};
use Mockery;
use RuntimeException;

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
        $repository->shouldReceive('tags')->once()->with(CachedPostsQuery::cacheBaseTag())->andReturnSelf();
        $repository->shouldReceive('flush')->once()->andReturn(true);

        Cache::shouldReceive('store')->once()->with('array')->andReturn($repository);

        $status = Artisan::call('sculptor:flush-cache', ['class' => CachedPostsQuery::class]);

        $this->assertSame(Command::SUCCESS, $status);

        $this->assertStringContainsString(
            sprintf('Cache flushed successfully for class "%s"', CachedPostsQuery::class),
            Artisan::output()
        );
    }

    public function test_command_reports_error_for_non_cacheable_class(): void
    {
        $status = Artisan::call('sculptor:flush-cache', ['class' => self::class]);

        $this->assertSame(Command::FAILURE, $status);

        $this->assertStringContainsString(
            sprintf('Expected an implementation of "%s"', WithCache::class),
            Artisan::output()
        );
    }

    public function test_command_fails_for_missing_class(): void
    {
        $status = Artisan::call('sculptor:flush-cache', ['class' => 'App\\Queries\\MissingQuery']);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('App\\Queries\\MissingQuery', Artisan::output());
    }

    public function test_command_fails_when_cache_backend_throws(): void
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('tags')->once()->with(CachedPostsQuery::cacheBaseTag())
            ->andThrow(new RuntimeException('Cache backend unavailable'));

        Cache::shouldReceive('store')->once()->with('array')->andReturn($repository);

        $status = Artisan::call('sculptor:flush-cache', ['class' => CachedPostsQuery::class]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Cache backend unavailable', Artisan::output());
    }

    public function test_command_fails_when_cache_backend_returns_false(): void
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('tags')->once()->with(CachedPostsQuery::cacheBaseTag())->andReturnSelf();
        $repository->shouldReceive('flush')->once()->andReturn(false);

        Cache::shouldReceive('store')->once()->with('array')->andReturn($repository);

        $status = Artisan::call('sculptor:flush-cache', ['class' => CachedPostsQuery::class]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Failed to flush cache', Artisan::output());
        $this->assertStringNotContainsString('Cache flushed successfully', Artisan::output());
    }
}
