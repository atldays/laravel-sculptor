<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\QueryCache\Query\Builder as CacheQueryBuilder;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Webmozart\Assert\Assert;

trait InteractsWithBuilderCache
{
    /**
     * @return CacheQueryBuilder&Builder
     */
    protected function applyBuilderCache(Builder $query): Builder
    {
        $baseQuery = $this->cacheableBaseQuery($query);

        $cacheFor = $this->cacheFor();

        $query
            ->cacheTags($this->cacheTags())
            ->cacheDriver($this->cacheStore());

        return (is_int($cacheFor) && $cacheFor <= 0) || $baseQuery->useWritePdo
            ? $query->dontCache()
            : $query->cacheFor($cacheFor);
    }

    private function cacheableBaseQuery(mixed $query): CacheQueryBuilder
    {
        $baseQuery = method_exists($query, 'getQuery') ? $query->getQuery() : $query;

        if ($baseQuery instanceof CacheQueryBuilder) {
            return $baseQuery;
        }

        if ($baseQuery !== $query && method_exists($baseQuery, 'getQuery')) {
            return $this->cacheableBaseQuery($baseQuery);
        }

        Assert::isInstanceOf($baseQuery, CacheQueryBuilder::class);

        return $baseQuery;
    }
}
