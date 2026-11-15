<?php

namespace Atldays\Sculptor\Tests\Fixtures\Filters;

use Atldays\EloquentFilters\Contracts\EloquentFilterContract;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class CallbackFilter implements EloquentFilterContract
{
    public function __construct(private readonly Closure $callback) {}

    public function apply(Builder $query): Builder
    {
        return ($this->callback)($query);
    }

    public function isApplicable(): bool
    {
        return true;
    }
}
