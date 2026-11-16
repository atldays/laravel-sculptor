<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\{WithQuery, WithResultCache};
use Illuminate\Contracts\Database\Eloquent\Builder;
use Webmozart\Assert\Assert;

/**
 * @mixin WithQuery
 *
 * @extends HasResult<int>
 */
trait QueryResultCount
{
    use HasResult;

    public function effect(): int
    {
        Assert::implementsInterface($this, WithQuery::class);

        if ($this instanceof WithResultCache) {
            return $this->rememberResult('count', fn (Builder $query) => $query->count());
        }

        return $this->query()->count();
    }
}
