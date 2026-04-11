<?php

namespace Atldays\Sculptor\Attributes\Contracts;

interface CacheStoreAttribute
{
    /**
     * @return string
     */
    public function store(): string;
}
