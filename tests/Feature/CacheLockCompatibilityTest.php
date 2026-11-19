<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\QueryCache\Cache\CacheLockOptions;
use Atldays\Sculptor\BaseQuery;
use Atldays\Sculptor\Concerns\{InteractsWithBuilderCache, InteractsWithResultCache, QueryResultAll};
use Atldays\Sculptor\Contracts\{WithBuilderCache, WithCache, WithResultCache};
use Atldays\Sculptor\Tests\Fixtures\Models\{LockDefaultPost, Post};
use Atldays\Sculptor\Tests\Support\RecordingLockStore;
use Atldays\Sculptor\Tests\TestCase;
use BadMethodCallException;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{Cache, DB};
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Stringable;

class CacheLockCompatibilityTest extends TestCase
{
    private RecordingLockStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new RecordingLockStore(serializesValues: true);
        $store = $this->store;
        Cache::extend('recording-lock', fn () => new Repository($store));
        config()->set('cache.stores.array', ['driver' => 'recording-lock']);
        Cache::forgetDriver('array');
        DB::table('authors')->insert(['id' => 42, 'name' => 'Owner']);
        DB::table('posts')->insert(['id' => 1, 'author_id' => 42, 'title' => 'Original']);
    }

    public static function implementations(): iterable
    {
        yield 'result without lock API' => [ManualResultCacheQuery::class, []];
        yield 'builder without lock API' => [ManualBuilderCacheQuery::class, []];
        yield 'result with own lock settings' => [ManualResultCacheWithLockQuery::class, [17]];
        yield 'builder with own lock settings' => [ManualBuilderCacheWithLockQuery::class, [17]];
    }

    #[DataProvider('implementations')]
    public function test_manual_cache_contracts_work_without_has_cache(string $class, array $leases): void
    {
        $query = new $class;
        $this->assertInstanceOf(WithCache::class, $query);
        $this->assertFalse(method_exists($query, 'setCacheLock'));
        $this->assertFalse(method_exists($query, 'withoutCacheLock'));
        $this->assertFalse(method_exists($query, 'hasCacheLockOverride'));
        $this->assertSame('Original', $query->effect()->sole()->title);
        $this->assertSame($leases, array_column($this->store->locksRequested, 1));
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame('Original', $query->effect()->sole()->title);
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame($leases, array_column($this->store->locksRequested, 1));
    }

    public function test_manual_builder_without_lock_methods_preserves_model_and_relation_defaults(): void
    {
        $query = new ManualBuilderCacheQuery(LockDefaultPost::class);
        $this->assertFalse(method_exists($query, 'cacheLockOptions'));
        $this->assertSame('Owner', $query->effect()->sole()->author->name);
        $this->assertSame([37, 43], array_column($this->store->locksRequested, 1));
    }

    public function test_null_custom_setting_is_no_override_of_model_defaults(): void
    {
        $query = new class(LockDefaultPost::class) extends ManualBuilderCacheQuery
        {
            public function cacheLockOptions(): ?CacheLockOptions
            {
                return null;
            }
        };
        $this->assertSame('Owner', $query->effect()->sole()->author->name);
        $this->assertSame([37, 43], array_column($this->store->locksRequested, 1));
    }

    public function test_custom_builder_lock_settings_reach_model_default_relations(): void
    {
        $query = new ManualBuilderCacheWithLockQuery(LockDefaultPost::class);
        $this->assertSame('Owner', $query->effect()->sole()->author->name);
        $this->assertSame([17, 17], array_column($this->store->locksRequested, 1));
    }
}

abstract class ManualCacheQuery extends BaseQuery implements WithCache
{
    protected string $model;

    public function __construct(string $model = Post::class)
    {
        $this->model = $model;
    }

    public static function flushCache(): bool
    {
        return static::flushCacheByTags(static::class);
    }

    public static function flushCacheByTags(string|Stringable $tag, string|Stringable ...$tags): bool
    {
        return Cache::store('array')->tags(array_map('strval', [$tag, ...$tags]))->flush();
    }

    public static function flushCacheByScope(mixed ...$arguments): bool
    {
        throw new LogicException('This query has no cache scopes.');
    }

    public function cacheFor(): int
    {
        return 600;
    }

    public function cacheTags(): array
    {
        return [static::class];
    }

    public function cacheStore(): string
    {
        return 'array';
    }

    public function getCache(): CacheRepository
    {
        return Cache::store($this->cacheStore())->tags($this->cacheTags());
    }

    public function __call(string $name, array $arguments): mixed
    {
        throw new BadMethodCallException('No dynamic method ' . $name);
    }
}

class ManualResultCacheQuery extends ManualCacheQuery implements WithResultCache
{
    use InteractsWithResultCache;
    use QueryResultAll;
}

class ManualBuilderCacheQuery extends ManualCacheQuery implements WithBuilderCache
{
    use InteractsWithBuilderCache;
    use QueryResultAll;

    public function query(): Builder
    {
        return $this->applyBuilderCache(parent::query());
    }
}

class ManualResultCacheWithLockQuery extends ManualResultCacheQuery
{
    public function cacheLockOptions(): ?CacheLockOptions
    {
        return new CacheLockOptions(17, 0);
    }
}

class ManualBuilderCacheWithLockQuery extends ManualBuilderCacheQuery
{
    public function cacheLockOptions(): ?CacheLockOptions
    {
        return new CacheLockOptions(17, 0);
    }
}
