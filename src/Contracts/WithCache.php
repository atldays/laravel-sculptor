<?php

namespace Atldays\Sculptor\Contracts;

use DateTime;
use Illuminate\Contracts\Cache\Repository as Cache;

interface WithCache
{
    public function cacheFor(): DateTime|int;

    /**
     * @return string[]
     */
    public function cacheTags(): array;

    public function getCache(): Cache;

    public function cacheStore(): string;

    public static function flushBaseCache(): bool;
}
