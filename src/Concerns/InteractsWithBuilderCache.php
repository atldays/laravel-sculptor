<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Concerns;

use Atldays\QueryCache\Query\Builder as CacheQueryBuilder;
use Atldays\Sculptor\Cache\CacheLockRelations;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        // @phpstan-ignore function.alreadyNarrowedType (Custom cache implementations may omit lock methods.)
        $lockOptions = method_exists($this, 'cacheLockOptions') ? $this->cacheLockOptions() : null;
        // @phpstan-ignore function.alreadyNarrowedType (Custom cache implementations may omit lock methods.)
        $lockOverridden = method_exists($this, 'hasCacheLockOverride') && $this->hasCacheLockOverride();

        if (($lockOptions !== null || $lockOverridden) && !$query instanceof Relation) {
            if ($lockOptions !== null) {
                $baseQuery->cacheLock($lockOptions->seconds, $lockOptions->waitSeconds);
            } else {
                $baseQuery->withoutCacheLock();
            }

            (new CacheLockRelations($query, $lockOptions))->prepare();
        }

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
