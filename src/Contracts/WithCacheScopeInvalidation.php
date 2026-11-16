<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Contracts;

interface WithCacheScopeInvalidation extends WithCacheTagInvalidation
{
    public static function flushScope(mixed ...$arguments): bool;
}
