<?php

namespace Atldays\Sculptor\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait PreparesCacheableRelations
{
    /**
     * @param  Collection<array-key, string|callable>  $relations
     * @return Collection<string, callable>
     */
    protected function prepareRelations(Collection $relations): Collection
    {
        if ($relations->isEmpty()) {
            return $relations;
        }

        return $relations->reduce(function (Collection $relations, string|callable $value, int|string $key) {
            $relation = $value;
            $closure = null;

            if (is_callable($value) && is_string($key)) {
                $relation = $key;
                $closure = $value;
            }

            return $relations->put($relation, function (Builder $query) use ($closure) {
                $this->applyBuilderCache($query);

                if ($closure) {
                    $closure($query);
                }
            });
        }, collect());
    }
}
