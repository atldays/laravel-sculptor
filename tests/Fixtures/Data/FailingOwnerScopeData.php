<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Data;

use RuntimeException;

readonly class FailingOwnerScopeData extends OwnerScopeData
{
    public function cacheScopeKey(): string
    {
        throw new RuntimeException('Custom scope unavailable');
    }
}
