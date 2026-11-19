<?php

namespace Atldays\Sculptor;

use Illuminate\Contracts\Database\Eloquent\Builder;

abstract class BuilderCachedQuery implements Contracts\WithBuilderCache, Contracts\WithLimit, Contracts\WithModel, Contracts\WithQuery
{
    use Concerns\HasCache;
    use Concerns\HasQueryWithFilters {
        query as protected filteredQuery;
    }
    use Concerns\InteractsWithBuilderCache;
    use Concerns\PreparesCacheableRelations {
        Concerns\PreparesCacheableRelations::prepareRelations insteadof Concerns\HasQueryWithFilters;
    }

    public function query(): Builder
    {
        return $this->applyBuilderCache($this->filteredQuery());
    }
}
