<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\{WithQuery, WithResultCache};
use Illuminate\Contracts\Database\Eloquent\Builder;
use Webmozart\Assert\Assert;

/**
 * @mixin WithQuery
 *
 * @extends HasResult<mixed>
 */
trait QueryResultAggregate
{
    use HasAggregateFunction;
    use HasResult;
    use HasResultColumn;

    public function effect(): mixed
    {
        Assert::implementsInterface($this, WithQuery::class);
        $column = $this->resultColumn();
        $function = $this->aggregateFunction()->value;
        $query = $this->query()->withoutEagerLoads();
        $execute = fn (Builder $query) => $query->{$function}($column);

        return $this instanceof WithResultCache
            ? $this->rememberResult('aggregate', $execute, [
                'aggregate_function' => $function,
                'result_column' => $this->resultColumnContext($column, $query),
            ], $query)
            : $execute($query);
    }
}
