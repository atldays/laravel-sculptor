<?php

namespace Atldays\Sculptor\Tests\Fixtures\Models;

use Atldays\QueryCache\Traits\QueryCacheable;

class CacheableProjectionPost extends ProjectionPost
{
    use QueryCacheable;
}
