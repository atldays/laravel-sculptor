<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\WithQuery;
use Atldays\Sculptor\Contracts\WithResultCache;
use Illuminate\Database\Eloquent\Model;
use Webmozart\Assert\Assert;

/**
 * @mixin WithQuery
 *
 * @template TModel of Model
 *
 * @property $model class-string<TModel>
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
            return $this->rememberResult('first', fn () => $this->query()->first());
        }

        return $this->query()->first();
    }
}
