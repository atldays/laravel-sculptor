<?php

namespace Atldays\Sculptor;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Atldays\QueryCache\Query\Builder as CacheQueryBuilder;
use Webmozart\Assert\Assert;

abstract class CacheQuery extends Query implements Contracts\WithCache
{
    use Concerns\HasCache;

    /**
     * @param Collection<array-key, string|callable> $relations
     * @return Collection<string, callable>
     */
    protected function prepareRelations(Collection $relations): Collection
    {
        if ($relations->isEmpty()) {
            return $relations;
        }

        $time = $this->cacheFor();
        $tags = $this->cacheTags();
        $store = $this->cacheStore();

        return $relations->reduce(function (Collection $relations, string|callable $value, int|string $key) use ($time, $tags, $store) {
            $relation = $value;
            $closure = null;

            if (is_callable($value) && is_string($key)) {
                $relation = $key;
                $closure = $value;
            }

            return $relations->put($relation, function (Builder $query) use ($time, $tags, $store, $closure) {
                /** @var $query CacheQueryBuilder&Builder */
                $query->cacheDriver($store)->cacheFor($time)->cacheTags($tags);

                if ($closure) {
                    $closure($query);
                }
            });
        }, collect());
    }

    /**
     * @return CacheQueryBuilder&Builder
     */
    public function query(): Builder
    {
        /** @var CacheQueryBuilder&Builder $query */
        $query = parent::query();

        Assert::isInstanceOf($query->getQuery(), CacheQueryBuilder::class);

        return $query
            ->cacheFor($this->cacheFor())
            ->cacheTags($this->cacheTags())
            ->cacheDriver($this->cacheStore());
    }
}
