<?php

namespace Atldays\Sculptor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Select
{
    /**
     * @param  string|list<string>  $columns
     */
    public function __construct(protected string|array $columns) {}

    /**
     * @return list<string>
     */
    public function columns(): array
    {
        return is_string($this->columns) ? [$this->columns] : array_values($this->columns);
    }
}
