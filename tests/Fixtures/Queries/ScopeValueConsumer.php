<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\CacheScope;

class ScopeValueConsumer
{
    public function __construct(#[CacheScope] public mixed $value) {}
}
