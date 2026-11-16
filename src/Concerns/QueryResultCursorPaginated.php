<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\WithCursorPaginatedResult;
use Atldays\Sculptor\Contracts\WithResultCache;
use Atldays\Sculptor\Data\CursorPaginationData;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator as CursorResolver;
use Illuminate\Pagination\Paginator;
use Webmozart\Assert\Assert;

trait QueryResultCursorPaginated
{
    use HasCursorPagination;
    use HasResult;

    public function effect(): CursorPaginator
    {
        Assert::true($this instanceof WithCursorPaginatedResult, 'Cursor paginated result support is required to paginate results');

        $query = $this->query();
        $configured = $this->cursorPaginationData();
        $cursor = is_string($configured->cursor)
            ? Cursor::fromEncoded($configured->cursor)
            : ($configured->cursor ?? CursorResolver::resolveCurrentCursor($configured->cursorName));

        $resolved = new CursorPaginationData(
            perPage: $configured->perPage ?? $query->getModel()->getPerPage(),
            cursor: $cursor,
            cursorName: $configured->cursorName,
            columns: $configured->columns,
        );

        if ($resolved->columns !== ['*']) {
            $query->select($resolved->columns);
        }

        $execute = fn (Builder $query) => $query->cursorPaginate(
            $resolved->perPage,
            $resolved->columns,
            $resolved->cursorName,
            // An empty string prevents Laravel from resolving a null cursor a second time.
            $resolved->cursor ?? '',
        );

        if ($this instanceof WithResultCache) {
            return clone $this->rememberResult('cursorPaginate', $execute, [
                'cursor_pagination' => $resolved->toArray(),
                'pagination_path' => Paginator::resolveCurrentPath(),
            ], $query);
        }

        return $execute($query);
    }
}
