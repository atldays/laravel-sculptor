<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Attributes\{CacheFor, DefaultCacheStore};
use Atldays\Sculptor\Attributes\Contracts\CacheStoreAttribute;
use Atldays\Sculptor\Support\{AttributeReader, CacheScopeResolver};
use DateTime;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Support\Facades\Cache;
use Stringable;
use Webmozart\Assert\Assert;

trait HasCache
{
    /**
     * The number of seconds or the DateTime instance
     * that overrides the attribute/default cache duration. Null means no override.
     */
    private int|DateTime|null $cacheFor = null;

    private bool $cacheDisabled = false;

    /**
     * The tags for the query cache. Can be useful
     * if flushing cache for specific tags only.
     */
    private array $cacheTags = [];

    /**
     * Get the cache driver instance.
     */
    public static function cache(): CacheContract
    {
        return Cache::store(static::resolveCacheStore());
    }

    private static function resolveCacheStore(): string
    {
        $attribute = AttributeReader::make(static::class)->get(CacheStoreAttribute::class) ?: new DefaultCacheStore;

        return $attribute->store();
    }

    /**
     * Get the base cache tag
     */
    public static function cacheBaseTag(): string
    {
        return 'sculptor:' . static::class;
    }

    public static function flushBaseCache(): bool
    {
        return static::cache()->tags(static::cacheBaseTag())->flush();
    }

    public static function flushCacheTags(
        string|Stringable $tag,
        string|Stringable ...$tags,
    ): bool {
        $tags = array_map('strval', [$tag, ...$tags]);

        foreach ($tags as $tag) {
            Assert::stringNotEmpty($tag);
        }

        return static::cache()->tags($tags)->flush();
    }

    public static function flushScope(mixed ...$arguments): bool
    {
        return static::flushCacheTags(CacheScopeResolver::make(static::class)->tagForInvalidation($arguments));
    }

    /**
     * @return $this
     */
    public function setCacheFor(int|DateTime $time): static
    {
        $this->cacheFor = $time;

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutCache(bool $withoutCache = true): static
    {
        $this->cacheDisabled = $withoutCache;

        return $this;
    }

    /**
     * @return $this
     */
    public function setCacheTags(string|Stringable ...$tags): static
    {
        $this->cacheTags = array_map('strval', $tags);

        return $this;
    }

    /**
     * @return $this
     */
    public function mergeCacheTags(string|Stringable ...$tags): static
    {
        $tags = array_merge($this->cacheTags, $tags);

        return $this->setCacheTags(...$tags);
    }

    public function cacheFor(): DateTime|int
    {
        if ($this->cacheDisabled) {
            return 0;
        }

        $cacheFor = $this->cacheFor
            ?? AttributeReader::make(static::class)->get(CacheFor::class)?->seconds()
            ?? 3600;

        if ($cacheFor instanceof DateTime || $cacheFor <= 0) {
            return $cacheFor;
        }

        return new DateTime("+$cacheFor seconds");
    }

    /**
     * @return string[]
     */
    public function cacheTags(): array
    {
        $tags = array_merge($this->cacheTags, CacheScopeResolver::make($this)->tags());

        return array_merge($tags, [static::cacheBaseTag()]);
    }

    final public function cacheStore(): string
    {
        return static::resolveCacheStore();
    }

    final public function getCache(): CacheContract
    {
        $tags = array_unique(array_merge([static::cacheBaseTag()], $this->cacheTags()));

        return static::cache()->tags($tags);
    }
}
