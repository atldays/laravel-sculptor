<?php

namespace Atldays\Sculptor\Data;

final readonly class PaginationData
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public int $perPage,
        public ?int $page,
        public string $pageName,
        public array $columns,
    ) {}

    /**
     * @return array{per_page:int,page:int|null,page_name:string,columns:list<string>}
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
