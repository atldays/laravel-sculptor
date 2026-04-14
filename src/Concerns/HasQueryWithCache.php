<?php

namespace Atldays\Sculptor\Concerns;

trait HasQueryWithCache
{
    use HasCache;
    use HasQuery;
    use InteractsWithResultCache;
}
