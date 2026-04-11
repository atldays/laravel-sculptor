<?php

namespace Atldays\Sculptor\Events;

final class ResultExecuted
{
    /**
     * @param string $name
     * @param float $startedAt
     * @param float $finishedAt
     */
    public function __construct(
        public string $name,
        public float $startedAt,
        public float $finishedAt,
    ) {
    }

    /**
     * @return float
     */
    public function duration(): float
    {
        return ($this->finishedAt - $this->startedAt) * 1000;
    }
}
