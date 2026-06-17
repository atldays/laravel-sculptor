<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Atldays\Sculptor\Contracts\WithResultCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Webmozart\Assert\Assert;

trait QueryResultPaginated
{
    use HasPagination;
    use HasResult;

    public function effect(): LengthAwarePaginator
    {
        Assert::true($this instanceof WithPaginatedResult, 'Paginated result support is required to paginate results');

        $pagination = $this->paginationData();

        if ($this instanceof WithResultCache) {
            return $this->rememberResult('paginate', fn () => $this->query()->paginate(
                $pagination->perPage,
                $pagination->columns,
                $pagination->pageName,
                $pagination->page,
            ));
        }

        return $this->query()->paginate(
            $pagination->perPage,
            $pagination->columns,
            $pagination->pageName,
            $pagination->page,
        );
    }
}
