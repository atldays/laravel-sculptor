<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\{WithQuery, WithResultCache};
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Webmozart\Assert\Assert;

/**
 * @mixin WithQuery
 *
 * @template TModel of Model
 *
 * @property class-string<TModel> $model
 *
 * @extends HasResult<TModel>
 */
trait QueryResultFirst
{
    use HasResult;

    /**
     * @return TModel|null
     */
    public function effect(): ?Model
    {
        Assert::implementsInterface($this, WithQuery::class);

        if ($this instanceof WithResultCache) {
            return $this->rememberResult('first', fn (Builder $query) => $query->first());
        }

        return $this->query()->first();
    }
}
