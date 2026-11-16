<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Contracts;

interface CacheScopeKey
{
    /**
     * Return a stable, application-defined component of the composite scope key.
     */
    public function cacheScopeKey(): string;
}
