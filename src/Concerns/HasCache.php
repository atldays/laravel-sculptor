<?php

namespace Atldays\Sculptor\Concerns;

use DateTime;
use Atldays\Sculptor\Attributes\Helpers\CacheAttributeProvider;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Support\Str;
use Stringable;

trait HasCache
{
    /**
     * The number of seconds or the DateTime instance
     * that specifies how long to cache the query.
     *
     * @var int|DateTime
     */
    private int|DateTime $cacheFor = 3600;

    /**
     * The tags for the query cache. Can be useful
     * if flushing cache for specific tags only.
     *
     * @var array
     */
    private array $cacheTags = [];

    /**
     * Get the cache driver instance.
     *
     * @return CacheContract
     */
    public static function cache(): CacheContract
    {
        return CacheAttributeProvider::resolve(static::class);
    }

    /**
     * Get the base cache tag
     *
     * @return string
     */
    public static function cacheBaseTag(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /**
     * @return bool
     */
    public static function flushBaseCache(): bool
    {
        return static::cache()->tags(static::cacheBaseTag())->flush();
    }

    /**
     * @param int|DateTime $time
     * @return $this
     */
    public function setCacheFor(int|DateTime $time): static
    {
        $this->cacheFor = $time;

        return $this;
    }

    /**
     * @param string|Stringable ...$tags
     * @return $this
     */
    public function setCacheTags(string|Stringable ...$tags): static
    {
        $this->cacheTags = array_map('strval', $tags);

        return $this;
    }

    /**
     * @param string|Stringable ...$tags
     * @return $this
     */
    public function mergeCacheTags(string|Stringable ...$tags): static
    {
        $tags = array_merge($this->cacheTags, $tags);

        return $this->setCacheTags(...$tags);
    }

    /**
     * @return DateTime|int
     */
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

    /**
     * @return string
     */
    final public function cacheStore(): string
    {
        return CacheAttributeProvider::make(static::class)->store();
    }

    /**
     * @return CacheContract
     */
    final public function getCache(): CacheContract
    {
        $tags = array_unique(array_merge([static::cacheBaseTag()], $this->cacheTags()));

        return static::cache()->tags($tags);
    }
}
