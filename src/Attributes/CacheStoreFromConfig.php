<?php

namespace Atldays\Sculptor\Attributes;

use Attribute;
use Illuminate\Support\Facades\Config;

#[Attribute(Attribute::TARGET_CLASS)]
class CacheStoreFromConfig extends DefaultCacheStore
{
    public function __construct(protected string $configKey, protected ?string $defaultStore = null) {}

    public function store(): string
    {
        return Config::get($this->configKey, $this->defaultStore) ?: parent::store();
    }
}
