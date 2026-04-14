<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\WithLimit;
use Atldays\Sculptor\Contracts\WithQuery;
use Atldays\Sculptor\Contracts\WithResultCache;
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
            return $this->rememberResult('collection', fn () => $this->query()->get());
        }

        return $this->query()->get();
    }
}
