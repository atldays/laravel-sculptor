<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Data\CursorPaginationData;
use Illuminate\Pagination\Cursor;

trait HasCursorPagination
{
    private ?int $perPage = null;

    private Cursor|string|null $cursor = null;

    private string $cursorName = 'cursor';

    /**
     * @var list<string>
     */
    private array $paginationColumns = ['*'];

    /**
     * @return $this
     */
    public function perPage(int $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * @return $this
     */
    public function cursor(Cursor|string|null $cursor): static
    {
        $this->cursor = $cursor;

        return $this;
    }

    /**
     * @return $this
     */
    public function cursorName(string $cursorName): static
    {
        $this->cursorName = $cursorName;

        return $this;
    }

    /**
     * @return $this
     */
    public function paginationColumns(string|array $columns): static
    {
        $this->paginationColumns = is_string($columns) ? [$columns] : array_values($columns);

        return $this;
    }

    public function cursorPaginationData(): CursorPaginationData
    {
        return new CursorPaginationData(
            perPage: $this->perPage,
            cursor: $this->cursor,
            cursorName: $this->cursorName,
            columns: $this->paginationColumns,
        );
    }
}
