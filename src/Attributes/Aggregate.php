<?php

namespace Atldays\Sculptor\Attributes;

use Atldays\Sculptor\Enums\AggregateFunction;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Aggregate
{
    protected AggregateFunction $function;

    public function __construct(AggregateFunction|string $function)
    {
        $this->function = AggregateFunction::resolve($function);
    }

    public function function(): AggregateFunction
    {
        return $this->function;
    }
}
