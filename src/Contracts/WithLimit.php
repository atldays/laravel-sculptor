<?php

namespace Atldays\Sculptor\Contracts;

interface WithLimit
{
    /**
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): static;

    /**
     * @return bool
     */
    public function hasLimit(): bool;

    /**
     * @return $this
     */
    public function withoutLimit(): static;
}
