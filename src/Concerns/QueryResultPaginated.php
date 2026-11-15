<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Atldays\Sculptor\Contracts\WithResultCache;
use Atldays\Sculptor\Data\PaginationData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Webmozart\Assert\Assert;

trait QueryResultPaginated
{
    use HasPagination;
    use HasResult;

    public function effect(): LengthAwarePaginator
    {
        Assert::true($this instanceof WithPaginatedResult, 'Paginated result support is required to paginate results');

        $query = $this->query();
        $configured = $this->paginationData();

        $resolved = new PaginationData(
            perPage: $configured->perPage ?? $query->getModel()->getPerPage(),
            page: $configured->page ?? Paginator::resolveCurrentPage($configured->pageName),
            pageName: $configured->pageName,
            columns: $configured->columns,
        );

        $execute = fn () => $query->paginate(
            $resolved->perPage,
            $resolved->columns,
            $resolved->pageName,
            $resolved->page,
        );

        if ($this instanceof WithResultCache) {
            return $this->rememberResult('paginate', $execute, [
                'pagination' => $resolved->toArray(),
                'pagination_path' => Paginator::resolveCurrentPath(),
            ]);
        }

        return $execute();
    }
}
