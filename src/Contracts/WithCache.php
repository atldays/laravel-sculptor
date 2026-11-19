<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Contracts;

use DateTime;
use Illuminate\Contracts\Cache\Repository as Cache;
use Stringable;

interface WithCache
{
    public static function flushCache(): bool;

    public static function flushCacheByTags(
        string|Stringable $tag,
        string|Stringable ...$tags,
    ): bool;

    /**
     * Invalidate entries matching every supplied marked constructor argument.
     * Omitted scope parameters are wildcards; at least one argument is required.
     */
    public static function flushCacheByScope(mixed ...$arguments): bool;

    public function cacheFor(): DateTime|int;

    /**
     * @return string[]
     */
    public function cacheTags(): array;

    public function getCache(): Cache;

    public function cacheStore(): string;
}
