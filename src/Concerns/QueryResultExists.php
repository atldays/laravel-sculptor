<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\{WithQuery, WithResultCache};
use Illuminate\Contracts\Database\Eloquent\Builder;
use Webmozart\Assert\Assert;

/**
 * @mixin WithQuery
 *
 * @extends HasResult<bool>
 */
trait QueryResultExists
{
    use HasResult;
    use PreparesQueryForExecution;

    public function effect(): bool
    {
        Assert::implementsInterface($this, WithQuery::class);

        if ($this instanceof WithResultCache) {
            return $this->rememberResult('exists', fn (Builder $query) => $query->exists());
        }

        return $this->queryForExecution()->exists();
    }
}
