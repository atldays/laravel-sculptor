<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Data;

use Atldays\Sculptor\Contracts\CacheScopeKey;

readonly class OwnerScopeData implements CacheScopeKey
{
    public function __construct(public int $ownerId, public string $tenant = 'default') {}

    public function cacheScopeKey(): string
    {
        return json_encode([$this->tenant, $this->ownerId], JSON_THROW_ON_ERROR);
    }
}
