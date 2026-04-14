<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\QueryCache\Query\Builder as CacheQueryBuilder;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Webmozart\Assert\Assert;

trait HasQueryWithBuilderCache
{
    use HasCache;
    use HasQuery {
        query as protected baseQuery;
    }
    use PreparesCacheableRelations {
        PreparesCacheableRelations::prepareRelations insteadof HasQuery;
    }

    /**
     * @return CacheQueryBuilder&Builder
     */
    public function query(): Builder
    {
        /** @var CacheQueryBuilder&Builder $query */
        $query = $this->baseQuery();

        Assert::isInstanceOf($query->getQuery(), CacheQueryBuilder::class);

        return $query
            ->cacheFor($this->cacheFor())
            ->cacheTags($this->cacheTags())
            ->cacheDriver($this->cacheStore());
    }
}
