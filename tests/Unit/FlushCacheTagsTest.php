<?php

namespace Atldays\Sculptor\Tests\Unit;

use ArgumentCountError;
use Atldays\Sculptor\Contracts\{WithBuilderCache, WithCache, WithCacheScopeInvalidation, WithCacheTagInvalidation, WithResultCache};
use Atldays\Sculptor\Tests\Fixtures\Queries\{CachedPostsQuery, ConfigStoreCachedPostsQuery, DefaultCacheConsumer, InheritedCachedPostsQuery, PlainCacheConsumer};
use Atldays\Sculptor\Tests\TestCase;
use BadMethodCallException;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Stringable;

class FlushCacheTagsTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::clearResolvedInstances();

        parent::tearDown();
    }

    public static function literalTags(): array
    {
        return [['0'], [' '], [' Projects '], ['PROJECT'], ['project']];
    }

    #[DataProvider('literalTags')]
    public function test_tags_preserve_zero_whitespace_and_case(string $tag): void
    {
        $repositories = [];

        foreach (['0', ' ', ' Projects ', 'Projects', 'PROJECT', 'project'] as $name) {
            $repository = (new PlainCacheConsumer)->setCacheTags($name)->getCache();
            $repository->put('result', $name, 60);
            $repositories[$name] = $repository;
        }

        $this->assertTrue(PlainCacheConsumer::flushCacheTags($tag));

        foreach ($repositories as $name => $repository) {
            $this->assertSame((string)$name === $tag ? null : (string)$name, $repository->get('result'));
        }
    }

    public function test_stringable_tags_are_converted_once_and_use_the_same_namespace_as_strings(): void
    {
        $first = (new PlainCacheConsumer)->setCacheTags('projects:user:42')->getCache();
        $second = (new PlainCacheConsumer)->setCacheTags('projects:user:57')->getCache();
        $unrelated = (new PlainCacheConsumer)->setCacheTags('projects:user:73')->getCache();

        foreach ([$first, $second, $unrelated] as $repository) {
            $repository->put('result', 'cached', 60);
        }

        $tag = new class() implements Stringable
        {
            public int $conversions = 0;

            public function __toString(): string
            {
                $this->conversions++;

                return 'projects:user:42';
            }
        };

        $this->assertTrue(PlainCacheConsumer::flushCacheTags($tag, str('projects:user:57')));
        $this->assertSame(1, $tag->conversions);
        $this->assertNull($first->get('result'));
        $this->assertNull($second->get('result'));
        $this->assertSame('cached', $unrelated->get('result'));
    }

    public static function emptyTags(): iterable
    {
        foreach ([false, true] as $stringable) {
            foreach ([false, true] as $laterArgument) {
                yield ($stringable ? 'Stringable' : 'string') . ($laterArgument ? ' later' : ' first') => [$stringable, $laterArgument];
            }
        }
    }

    #[DataProvider('emptyTags')]
    public function test_all_tags_are_validated_before_accessing_the_store(bool $stringable, bool $laterArgument): void
    {
        $repository = (new PlainCacheConsumer)->setCacheTags('projects:user:42')->getCache();
        $repository->put('result', 'preserved', 60);
        $empty = $stringable ? str('') : '';
        $tags = $laterArgument ? ['projects:user:42', $empty] : [$empty, 'projects:user:42'];
        Cache::shouldReceive('store')->never();

        try {
            PlainCacheConsumer::flushCacheTags(...$tags);
            $this->fail('An empty tag must be rejected before invalidation.');
        } catch (InvalidArgumentException) {
            $this->assertSame('preserved', $repository->get('result'));
        }
    }

    public function test_at_least_one_tag_is_required(): void
    {
        $this->expectException(ArgumentCountError::class);

        PlainCacheConsumer::flushCacheTags();
    }

    public static function flushResults(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('flushResults')]
    public function test_the_store_result_is_returned_without_adding_tags(bool $result): void
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('tags')->once()->with(['projects:user:42', 'projects:user:57'])->andReturnSelf();
        $repository->shouldReceive('flush')->once()->andReturn($result);
        Cache::shouldReceive('store')->once()->with('array')->andReturn($repository);

        $this->assertSame($result, PlainCacheConsumer::flushCacheTags('projects:user:42', 'projects:user:57'));
    }

    public function test_store_exceptions_are_propagated_unchanged(): void
    {
        $exception = new RuntimeException('Cache unavailable');
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('tags')->once()->with(['projects:user:42'])->andReturnSelf();
        $repository->shouldReceive('flush')->once()->andThrow($exception);
        Cache::shouldReceive('store')->once()->with('array')->andReturn($repository);

        try {
            PlainCacheConsumer::flushCacheTags('projects:user:42');
            $this->fail('The store exception must be propagated.');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }
    }

    public static function storeConfigurations(): array
    {
        return [
            'package configuration' => [DefaultCacheConsumer::class, 'secondary', 'array', 'secondary'],
            'direct attribute' => [CachedPostsQuery::class, 'secondary', 'array', 'array'],
            'inherited attribute' => [InheritedCachedPostsQuery::class, 'secondary', 'array', 'array'],
            'config attribute' => [ConfigStoreCachedPostsQuery::class, 'array', 'secondary', 'secondary'],
        ];
    }

    #[DataProvider('storeConfigurations')]
    public function test_only_the_configured_store_is_invalidated(string $class, string $packageStore, string $attributeStore, string $expectedStore): void
    {
        config()->set('cache.stores.secondary', ['driver' => 'array']);
        config()->set('sculptor.cache_store', $packageStore);
        config()->set('services.sculptor.cache_store', $attributeStore);
        $primary = Cache::store('array')->tags('projects:user:42');
        $secondary = Cache::store('secondary')->tags('projects:user:42');
        $primary->put('result', 'primary', 60);
        $secondary->put('result', 'secondary', 60);

        $this->assertTrue($class::flushCacheTags('projects:user:42'));
        $this->assertSame($expectedStore === 'array' ? null : 'primary', $primary->get('result'));
        $this->assertSame($expectedStore === 'secondary' ? null : 'secondary', $secondary->get('result'));
    }

    public function test_unsupported_stores_throw_without_falling_back_to_global_flush(): void
    {
        $path = sys_get_temp_dir() . '/sculptor-cache-tags-' . bin2hex(random_bytes(8));
        config()->set('cache.stores.untagged', ['driver' => 'file', 'path' => $path]);
        config()->set('sculptor.cache_store', 'untagged');
        $repository = Cache::store('untagged');
        $repository->put('unrelated', 'preserved', 60);

        try {
            try {
                DefaultCacheConsumer::flushCacheTags('projects:user:42');
                $this->fail('A store without tags must reject invalidation.');
            } catch (BadMethodCallException) {
                $this->assertSame('preserved', $repository->get('unrelated'));
            }
        } finally {
            $this->app['files']->deleteDirectory($path);
        }
    }

    public function test_legacy_cache_interfaces_do_not_require_the_new_capability(): void
    {
        // This deliberately implements the old contracts without using HasCache.
        $legacy = new class() implements WithBuilderCache, WithResultCache
        {
            public function cacheFor(): int
            {
                return 60;
            }

            public function cacheTags(): array
            {
                return [];
            }

            public function getCache(): CacheRepository
            {
                return Cache::store('array');
            }

            public function cacheStore(): string
            {
                return 'array';
            }

            public static function flushBaseCache(): bool
            {
                return true;
            }
        };

        $this->assertInstanceOf(WithCache::class, $legacy);
        $this->assertNotInstanceOf(WithCacheTagInvalidation::class, $legacy);
        $this->assertNotInstanceOf(WithCacheScopeInvalidation::class, $legacy);
        $this->assertFalse(method_exists($legacy, 'flushCacheTags'));
        $this->assertFalse(method_exists($legacy, 'flushScope'));
    }
}
