<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\{WithLimit, WithQuery, WithResultCache};
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Webmozart\Assert\Assert;

/**
 * @mixin WithQuery&WithLimit
 *
 * @template TModel of Model
 *
 * @property $model class-string<TModel>
 *
 * @extends HasResult<Collection<array-key, TModel>>
 */
trait QueryResultCollection
{
    use HasResult;

    /**
     * @return Collection<array-key, TModel>
     */
    public function effect(): Collection
    {
        Assert::true($this instanceof WithQuery && $this instanceof WithLimit, 'Query and Limit are required to fetch collection');
        Assert::true($this->hasLimit(), 'Limit is required to fetch collection');

        if ($this instanceof WithResultCache) {
            return $this->rememberResult('collection', fn (Builder $query) => $query->get());
        }

        return $this->query()->get();
    }
}
