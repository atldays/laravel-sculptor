<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\QueryCache\Query\Builder as CacheQueryBuilder;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Webmozart\Assert\Assert;

trait HasQueryWithFiltersAndBuilderCache
{
    use HasCache;
    use HasQueryWithFilters {
        query as protected filteredQuery;
    }
    use PreparesCacheableRelations {
        PreparesCacheableRelations::prepareRelations insteadof HasQueryWithFilters;
    }

    /**
     * @return CacheQueryBuilder&Builder
     */
    public function query(): Builder
    {
        /** @var CacheQueryBuilder&Builder $query */
        $query = $this->filteredQuery();

        Assert::isInstanceOf($query->getQuery(), CacheQueryBuilder::class);

        return $query
            ->cacheFor($this->cacheFor())
            ->cacheTags($this->cacheTags())
            ->cacheDriver($this->cacheStore());
    }
}
