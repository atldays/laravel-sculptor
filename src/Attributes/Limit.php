<?php

namespace Atldays\Sculptor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Limit
{
    public function __construct(protected int $limit) {}

    public function limit(): int
    {
        return $this->limit;
    }
}
