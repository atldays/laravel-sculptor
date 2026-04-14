<?php

namespace Atldays\Sculptor\Contracts;

use Atldays\Sculptor\Data\PaginationData;

interface WithPaginationData
{
    public function perPage(int $perPage): static;

    public function page(int $page): static;

    public function pageName(string $pageName): static;

    public function paginationColumns(string|array $columns): static;

    public function paginationData(): PaginationData;
}
