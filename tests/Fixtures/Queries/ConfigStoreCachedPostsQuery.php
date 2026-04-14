<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\CacheStoreFromConfig;

#[CacheStoreFromConfig('services.sculptor.cache_store', 'array')]
class ConfigStoreCachedPostsQuery extends CachedPostsQuery {}
