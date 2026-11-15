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
        $this->assertCacheableBuilder($query);

        $cacheFor = $this->cacheFor();

        $query
            ->cacheTags($this->cacheTags())
            ->cacheDriver($this->cacheStore());

        return is_int($cacheFor) && $cacheFor <= 0
            ? $query->dontCache()
            : $query->cacheFor($cacheFor);
    }

    private function assertCacheableBuilder(mixed $query): void
    {
        $baseQuery = method_exists($query, 'getQuery') ? $query->getQuery() : $query;

        if ($baseQuery instanceof CacheQueryBuilder) {
            return;
        }

        if ($baseQuery !== $query && method_exists($baseQuery, 'getQuery')) {
            $this->assertCacheableBuilder($baseQuery);

            return;
        }

        Assert::isInstanceOf($baseQuery, CacheQueryBuilder::class);
    }
}
