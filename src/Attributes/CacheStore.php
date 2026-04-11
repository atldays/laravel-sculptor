<?php

namespace Atldays\Sculptor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class CacheStore implements Contracts\CacheStoreAttribute
{
    /**
     * @param string $store
     */
    public function __construct(protected string $store)
    {
    }

    /**
     * @return string
     */
    public function store(): string
    {
        return $this->store;
    }
}
