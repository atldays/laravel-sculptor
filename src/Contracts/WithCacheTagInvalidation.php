<?php

namespace Atldays\Sculptor\Contracts;

use Stringable;

interface WithCacheTagInvalidation extends WithCache
{
    public static function flushCacheTags(
        string|Stringable $tag,
        string|Stringable ...$tags,
    ): bool;
}
