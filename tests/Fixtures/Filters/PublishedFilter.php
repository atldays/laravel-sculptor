<?php

namespace Atldays\Sculptor\Tests\Fixtures\Filters;

use Atldays\EloquentFilters\Contracts\EloquentFilterContract;
use Illuminate\Database\Eloquent\Builder;

class PublishedFilter implements EloquentFilterContract
{
    public function apply(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function isApplicable(): bool
    {
        return true;
    }
}
