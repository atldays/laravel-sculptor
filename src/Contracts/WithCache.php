<?php

namespace Atldays\Sculptor\Contracts;

use DateTime;
use Illuminate\Contracts\Cache\Repository as Cache;

interface WithCache
{
    /**
     * @return DateTime|int
     */
    public function cacheFor(): DateTime|int;

    /**
     * @return string[]
     */
    public function cacheTags(): array;

    /**
     * @return Cache
     */
    public function getCache(): Cache;

    /**
     * @return bool
     */
    public static function flushBaseCache(): bool;
}
