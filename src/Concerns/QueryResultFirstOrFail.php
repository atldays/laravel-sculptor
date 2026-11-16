<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\{WithQuery, WithResultCache};
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\{Model, ModelNotFoundException};
use Webmozart\Assert\Assert;

/**
 * @mixin WithQuery
 *
 * @template TModel of Model
 *
 * @extends HasResult<TModel>
 */
trait QueryResultFirstOrFail
{
    use HasResult;

    /**
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function effect(): Model
    {
        Assert::implementsInterface($this, WithQuery::class);

        if ($this instanceof WithResultCache) {
            return $this->rememberResult('firstOrFail', fn (Builder $query) => $query->firstOrFail());
        }

        return $this->query()->firstOrFail();
    }
}
