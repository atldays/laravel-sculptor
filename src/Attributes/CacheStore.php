<?php

namespace Atldays\Sculptor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class CacheStore implements Contracts\CacheStoreAttribute
{
    public function __construct(protected string $store) {}

    public function store(): string
    {
        return $this->store;
    }
}
