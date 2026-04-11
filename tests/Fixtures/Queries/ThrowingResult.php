<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\HasResult;
use RuntimeException;

class ThrowingResult
{
    use HasResult;

    public function effect(): never
    {
        throw new RuntimeException('Boom');
    }
}
