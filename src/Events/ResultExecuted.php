<?php

namespace Atldays\Sculptor\Events;

final class ResultExecuted
{
    public function __construct(
        public string $name,
        public float $startedAt,
        public float $finishedAt,
    ) {}

    public function duration(): float
    {
        return ($this->finishedAt - $this->startedAt) * 1000;
    }
}
