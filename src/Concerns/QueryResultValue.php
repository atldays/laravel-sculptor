<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\{WithQuery, WithResultCache};
use Illuminate\Contracts\Database\Eloquent\Builder;
use Webmozart\Assert\Assert;

/**
 * @mixin WithQuery
 *
 * @template TValue
 *
 * @extends HasResult<TValue|null>
 */
trait QueryResultValue
{
    use HasResult;
    use HasResultColumn;

    public function effect(): mixed
    {
        Assert::implementsInterface($this, WithQuery::class);
        $column = $this->resultColumn();
        $query = $this->resultProjectionQuery([$column]);
        $execute = fn (Builder $query) => $query->value($column);

        return $this instanceof WithResultCache
            ? $this->rememberResult('value', $execute, ['result_column' => $this->resultColumnContext($column, $query)], $query)
            : $execute($query);
    }
}
