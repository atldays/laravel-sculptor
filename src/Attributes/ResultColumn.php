<?php

namespace Atldays\Sculptor\Attributes;

use Attribute;
use Illuminate\Contracts\Database\Query\Expression;
use Webmozart\Assert\Assert;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class ResultColumn
{
    public function __construct(protected string|Expression $column)
    {
        if (is_string($column)) {
            Assert::notWhitespaceOnly($column, 'Result column must not be empty.');
        }
    }

    public function column(): string|Expression
    {
        return $this->column;
    }
}
