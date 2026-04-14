<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\Concerns\HasCache;

#[CacheStore('array')]
class PlainCacheConsumer
{
    use HasCache;
}
