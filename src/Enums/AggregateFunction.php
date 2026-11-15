<?php

namespace Atldays\Sculptor\Enums;

use Webmozart\Assert\Assert;

enum AggregateFunction: string
{
    case Sum = 'sum';
    case Avg = 'avg';
    case Min = 'min';
    case Max = 'max';

    public static function resolve(self|string $function): self
    {
        if ($function instanceof self) {
            return $function;
        }

        $resolved = self::tryFrom(strtolower($function));
        Assert::notNull($resolved, 'Aggregate function must be sum, avg, min, or max.');

        return $resolved;
    }
}
