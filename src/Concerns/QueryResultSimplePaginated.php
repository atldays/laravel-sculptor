<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Atldays\Sculptor\Contracts\WithResultCache;
use Atldays\Sculptor\Data\PaginationData;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\Paginator as PaginationResolver;
use Webmozart\Assert\Assert;

trait QueryResultSimplePaginated
{
    use HasPagination;
    use HasResult;

    public function effect(): Paginator
    {
        Assert::true($this instanceof WithPaginatedResult, 'Paginated result support is required to paginate results');

        $query = $this->query();
        $configured = $this->paginationData();

        $resolved = new PaginationData(
            perPage: $configured->perPage ?? $query->getModel()->getPerPage(),
            page: $configured->page ?? PaginationResolver::resolveCurrentPage($configured->pageName),
            pageName: $configured->pageName,
            columns: $configured->columns,
        );

        if ($resolved->columns !== ['*']) {
            $query->select($resolved->columns);
        }

        $execute = fn (Builder $query) => $query->simplePaginate(
            $resolved->perPage,
            $resolved->columns,
            $resolved->pageName,
            $resolved->page,
        );

        if ($this instanceof WithResultCache) {
            return clone $this->rememberResult('simplePaginate', $execute, [
                'pagination' => $resolved->toArray(),
                'pagination_path' => PaginationResolver::resolveCurrentPath(),
            ], $query);
        }

        return $execute($query);
    }
}
