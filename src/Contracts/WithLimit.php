<?php

namespace Atldays\Sculptor\Contracts;

interface WithLimit
{
    /**
     * @return $this
     */
    public function limit(int $limit): static;

    public function hasLimit(): bool;

    /**
     * @return $this
     */
    public function withoutLimit(): static;
}
