<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Tests\Fixtures\Queries\CachedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\ConfigStoreCachedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\InheritedCachedPostsQuery;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\DatabaseStore;

class HasCacheStoreTest extends TestCase
{
    public function test_it_resolves_store_from_direct_attribute(): void
    {
        $query = new CachedPostsQuery;

        $this->assertSame('array', $query->cacheStore());
        $this->assertInstanceOf(ArrayStore::class, CachedPostsQuery::cache()->getStore());
    }

    public function test_it_inherits_store_attribute_from_parent_class(): void
    {
        $query = new InheritedCachedPostsQuery;

        $this->assertSame('array', $query->cacheStore());
        $this->assertInstanceOf(ArrayStore::class, InheritedCachedPostsQuery::cache()->getStore());
    }

    public function test_it_resolves_store_from_config_attribute(): void
    {
        config()->set('services.sculptor.cache_store', 'database');

        $query = new ConfigStoreCachedPostsQuery;

        $this->assertSame('database', $query->cacheStore());
        $this->assertInstanceOf(DatabaseStore::class, ConfigStoreCachedPostsQuery::cache()->getStore());
    }
}
