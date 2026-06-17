<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Attributes\Contracts\CacheStoreAttribute;
use Atldays\Sculptor\Attributes\DefaultCacheStore;
use Atldays\Sculptor\Attributes\Helpers\AttributeReader;
use DateTime;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Stringable;

trait HasCache
{
    /**
     * The number of seconds or the DateTime instance
     * that specifies how long to cache the query.
     */
    private int|DateTime $cacheFor = 3600;

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
        return Str::snake(class_basename(static::class));
    }

    public static function flushBaseCache(): bool
    {
        return static::cache()->tags(static::cacheBaseTag())->flush();
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
        if ($this->cacheFor instanceof DateTime) {
            return $this->cacheFor;
        } elseif (is_int($this->cacheFor) && $this->cacheFor > 0) {
            return new DateTime("+$this->cacheFor seconds");
        }

        return new DateTime('+1 hour');
    }

    /**
     * @return string[]
     */
    public function cacheTags(): array
    {
        return array_merge($this->cacheTags, [static::cacheBaseTag()]);
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
