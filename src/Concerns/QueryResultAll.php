<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\{WithQuery, WithResultCache};
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\{Collection, Model};
use Webmozart\Assert\Assert;

/**
 * @mixin WithQuery
 *
 * @template TModel of Model
 *
 * @extends HasResult<Collection<array-key, TModel>>
 */
trait QueryResultAll
{
    use HasResult;
    use PreparesQueryForExecution;

    /**
     * @return Collection<array-key, TModel>
     */
    public function effect(): Collection
    {
        Assert::implementsInterface($this, WithQuery::class);

        if ($this instanceof WithResultCache) {
            return $this->rememberResult('all', fn (Builder $query) => $query->get());
        }

        return $this->queryForExecution()->get();
    }
}
