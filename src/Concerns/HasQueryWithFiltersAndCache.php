<?php

namespace Atldays\Sculptor\Concerns;

trait HasQueryWithFiltersAndCache
{
    use HasCache;
    use HasQueryWithFilters;
    use InteractsWithResultCache;
}
