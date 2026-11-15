<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Attributes\Aggregate;
use Atldays\Sculptor\Attributes\Helpers\AttributeReader;
use Atldays\Sculptor\Enums\AggregateFunction;
use Webmozart\Assert\Assert;

trait HasAggregateFunction
{
    private ?Aggregate $aggregateOverride = null;

    public function withAggregateFunction(AggregateFunction|string $function): static
    {
        $this->aggregateOverride = new Aggregate($function);

        return $this;
    }

    public function aggregateFunction(): AggregateFunction
    {
        $attribute = $this->aggregateOverride ?? AttributeReader::make($this)->get(Aggregate::class);
        Assert::isInstanceOf($attribute, Aggregate::class, 'An aggregate function is required. Use #[Aggregate] or withAggregateFunction().');

        return $attribute->function();
    }
}
