<?php

namespace Atldays\Sculptor\Contracts;

use Atldays\Sculptor\Data\CursorPaginationData;
use Illuminate\Pagination\Cursor;

interface WithCursorPaginationData
{
    public function perPage(int $perPage): static;

    public function cursor(Cursor|string|null $cursor): static;

    public function cursorName(string $cursorName): static;

    public function paginationColumns(string|array $columns): static;

    public function cursorPaginationData(): CursorPaginationData;
}
