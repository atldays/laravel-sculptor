<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Cache\TransactionCacheGuard;
use Atldays\Sculptor\Contracts\{WithQuery, WithResultCache, WithoutCacheInTransactions};
use Illuminate\Contracts\Database\Eloquent\Builder;
use Webmozart\Assert\Assert;

/**
 * SQL preparation for query result traits, independent of result lifecycle.
 */
trait PreparesQueryForExecution
{
    protected function queryForExecution(): Builder
    {
        Assert::isInstanceOf($this, WithQuery::class);

        return $this->prepareQueryForExecution($this->query());
    }

    protected function prepareQueryForExecution(Builder $query): Builder
    {
        // Result-cache execution prepares the operation in rememberResult().
        if (!$this instanceof WithoutCacheInTransactions || $this instanceof WithResultCache) {
            return $query;
        }

        $cacheFor = $this->cacheFor();

        return TransactionCacheGuard::make($query)->prepare(is_int($cacheFor) && $cacheFor <= 0);
    }
}
