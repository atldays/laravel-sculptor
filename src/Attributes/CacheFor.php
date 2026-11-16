<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class CacheFor
{
    public function __construct(protected int $seconds) {}

    public function seconds(): int
    {
        return $this->seconds;
    }
}
