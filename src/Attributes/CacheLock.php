<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Attributes;

use Atldays\QueryCache\Cache\CacheLockOptions;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class CacheLock
{
    private CacheLockOptions $options;

    public function __construct(int $seconds = 30, int $waitSeconds = 5)
    {
        $this->options = new CacheLockOptions($seconds, $waitSeconds);
    }

    public function options(): CacheLockOptions
    {
        return $this->options;
    }
}
