<?php

namespace Atldays\Sculptor\Attributes;

use Attribute;
use Illuminate\Support\Facades\Config;

#[Attribute(Attribute::TARGET_CLASS)]
class CacheStoreFromConfig extends DefaultCacheStore
{
    /**
     * @param string $configKey
     * @param string|null $defaultStore
     */
    public function __construct(protected string $configKey, protected ?string $defaultStore = null)
    {
    }

    /**
     * @return string
     */
    public function store(): string
    {
        return Config::get($this->configKey, $this->defaultStore) ?: parent::store();
    }
}
