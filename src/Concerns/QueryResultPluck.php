<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\WithQuery;
use Atldays\Sculptor\Contracts\WithResultCache;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Webmozart\Assert\Assert;

/**
 * @mixin WithQuery
 *
 * @template TValue
 *
 * @extends HasResult<Collection<array-key, TValue>>
 */
trait QueryResultPluck
{
    use HasPluckKey;
    use HasResult;
    use HasResultColumn;

    /**
     * @return Collection<array-key, TValue>
     */
    public function effect(): Collection
    {
        Assert::implementsInterface($this, WithQuery::class);
        $column = $this->resultColumn();
        $key = $this->pluckKey();
        $query = $this->resultProjectionQuery($key === null || $key === $column ? [$column] : [$column, $key]);
        $execute = fn (Builder $query) => $query->pluck($column, $key);

        return $this instanceof WithResultCache
            ? $this->rememberResult('pluck', $execute, [
                'result_column' => $this->resultColumnContext($column, $query),
                'pluck_key' => $key === null ? null : $this->resultColumnContext($key, $query),
            ], $query)
            : $execute($query);
    }
}
