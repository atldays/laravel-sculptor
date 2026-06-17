<?php

namespace Atldays\Sculptor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class WithRelations
{
    /**
     * @param  string|list<string>  $relations
     */
    public function __construct(protected string|array $relations) {}

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return is_string($this->relations) ? [$this->relations] : array_values($this->relations);
    }
}
