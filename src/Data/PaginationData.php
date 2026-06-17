<?php

namespace Atldays\Sculptor\Data;

use Webmozart\Assert\Assert;

final readonly class PaginationData
{
    public ?int $perPage;

    public ?int $page;

    public string $pageName;

    /**
     * @var list<string>
     */
    public array $columns;

    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        ?int $perPage,
        ?int $page,
        string $pageName,
        array $columns,
    ) {
        if ($perPage !== null) {
            Assert::greaterThanEq($perPage, 1);
        }

        if ($page !== null) {
            Assert::greaterThanEq($page, 1);
        }

        Assert::stringNotEmpty($pageName);
        Assert::notEmpty($columns);

        $this->perPage = $perPage;
        $this->page = $page;
        $this->pageName = $pageName;
        $this->columns = array_values($columns);
    }

    /**
     * @return array{per_page:int|null,page:int|null,page_name:string,columns:list<string>}
     */
    public function toArray(): array
    {
        return [
            'per_page' => $this->perPage,
            'page' => $this->page,
            'page_name' => $this->pageName,
            'columns' => $this->columns,
        ];
    }
}
