<?php

namespace Atldays\Sculptor;

abstract class BuilderCachedQuery implements Contracts\WithBuilderCache, Contracts\WithLimit, Contracts\WithModel, Contracts\WithQuery
{
    use Concerns\HasQueryWithFiltersAndBuilderCache;
}
