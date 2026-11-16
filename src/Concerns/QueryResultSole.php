<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\{WithQuery, WithResultCache};
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\{Model, ModelNotFoundException};
use Illuminate\Database\MultipleRecordsFoundException;
use Webmozart\Assert\Assert;

/**
 * @mixin WithQuery
 *
 * @template TModel of Model
 *
 * @extends HasResult<TModel>
 */
trait QueryResultSole
{
    use HasResult;

    /**
     * @return TModel
     *
     * @throws ModelNotFoundException
     * @throws MultipleRecordsFoundException
     */
    public function effect(): Model
    {
        Assert::implementsInterface($this, WithQuery::class);

        if ($this instanceof WithResultCache) {
            return $this->rememberResult('sole', fn (Builder $query) => $query->sole());
        }

        return $this->query()->sole();
    }
}
