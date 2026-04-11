<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Attributes\DefaultCacheStore;
use Atldays\Sculptor\Tests\Fixtures\Queries\DefaultCacheConsumer;
use Atldays\Sculptor\Tests\TestCase;

class DefaultCacheStoreTest extends TestCase
{
    public function test_default_cache_store_uses_package_config(): void
    {
        config()->set('sculptor.cache_store', 'database');

        $this->assertSame('database', (new DefaultCacheStore)->store());
        $this->assertSame('database', (new DefaultCacheConsumer)->cacheStore());
    }

    public function test_default_cache_store_falls_back_to_default_cache_driver(): void
    {
        config()->set('sculptor', []);
        config()->set('cache.default', 'array');

        $this->assertSame('array', (new DefaultCacheStore)->store());
    }
}
