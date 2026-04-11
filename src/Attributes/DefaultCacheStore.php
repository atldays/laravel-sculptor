<?php

namespace Atldays\Sculptor\Attributes;

use Attribute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

#[Attribute(Attribute::TARGET_CLASS)]
class DefaultCacheStore implements Contracts\CacheStoreAttribute
{
    public function store(): string
    {
        return Config::get('sculptor.cache_store', Cache::getDefaultDriver());
    }
}
