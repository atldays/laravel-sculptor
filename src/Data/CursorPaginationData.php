<?php

namespace Atldays\Sculptor\Data;

use Illuminate\Pagination\Cursor;
use Webmozart\Assert\Assert;

final readonly class CursorPaginationData
{
    public ?int $perPage;

    public Cursor|string|null $cursor;

    public string $cursorName;

    /**
     * @var list<string>
     */
    public array $columns;

    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        ?int $perPage,
        Cursor|string|null $cursor,
        string $cursorName,
        array $columns,
    ) {
        if ($perPage !== null) {
            Assert::greaterThanEq($perPage, 1);
        }

        Assert::stringNotEmpty($cursorName);
        Assert::notEmpty($columns);

        $this->perPage = $perPage;
        $this->cursor = $cursor;
        $this->cursorName = $cursorName;
        $this->columns = array_values($columns);
    }

    /**
     * @return array{per_page:int|null,cursor:string|null,cursor_name:string,columns:list<string>}
     */
    public function toArray(): array
    {
        return [
            'per_page' => $this->perPage,
            'cursor' => $this->cursor instanceof Cursor ? $this->cursor->encode() : $this->cursor,
            'cursor_name' => $this->cursorName,
            'columns' => $this->columns,
        ];
    }
}
