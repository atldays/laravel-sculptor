<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Data\PaginationData;

trait HasPagination
{
    private ?int $perPage = null;

    private ?int $page = null;

    private string $pageName = 'page';

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
    public function page(int $page): static
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @return $this
     */
    public function pageName(string $pageName): static
    {
        $this->pageName = $pageName;

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

    public function paginationData(): PaginationData
    {
        return new PaginationData(
            perPage: $this->perPage,
            page: $this->page,
            pageName: $this->pageName,
            columns: $this->paginationColumns,
        );
    }
}
