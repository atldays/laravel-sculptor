<?php

namespace Atldays\Sculptor;

abstract class CachedQuery implements Contracts\WithLimit, Contracts\WithModel, Contracts\WithQuery, Contracts\WithResultCache
{
    use Concerns\HasCache;
    use Concerns\HasQueryWithFilters;
    use Concerns\InteractsWithResultCache;
}
