<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\EloquentFilters\Contracts\EloquentFilterContract;
use Atldays\EloquentFilters\EloquentFilters;
use Illuminate\Contracts\Database\Eloquent\Builder;

trait HasQueryWithFilters
{
    use HasQuery {
        query as protected baseQuery;
    }

    private ?EloquentFilters $filters = null;

    /**
     * @return $this
     */
    public function addFilter(EloquentFilterContract ...$filter): static
    {
        ($this->filters ??= new EloquentFilters)->push(...$filter);

        return $this;
    }

    public function query(): Builder
    {
        $query = $this->baseQuery();

        if (($filters = $this->filters)?->isNotEmpty()) {
            $query->filter($filters);
        }

        return $query;
    }
}
