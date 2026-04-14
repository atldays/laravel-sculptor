<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\HasResult;

class ParameterizedResult
{
    use HasResult;

    public function __construct(private readonly string $value) {}

    public function effect(): string
    {
        return $this->value;
    }
}
