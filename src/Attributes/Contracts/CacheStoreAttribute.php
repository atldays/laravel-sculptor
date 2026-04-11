<?php

namespace Atldays\Sculptor\Attributes\Contracts;

interface CacheStoreAttribute
{
    public function store(): string;
}
