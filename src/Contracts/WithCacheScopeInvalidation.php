<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Contracts;

interface WithCacheScopeInvalidation extends WithCacheTagInvalidation
{
    /**
     * Invalidate entries matching every supplied marked constructor argument.
     * Omitted scope parameters are wildcards; at least one argument is required.
     */
    public static function flushScope(mixed ...$arguments): bool;
}
