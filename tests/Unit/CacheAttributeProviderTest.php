<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Attributes\Helpers\CacheAttributeProvider;
use Atldays\Sculptor\Tests\Fixtures\Queries\CachedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\ConfigStoreCachedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\InheritedCachedPostsQuery;
use Atldays\Sculptor\Tests\TestCase;

class CacheAttributeProviderTest extends TestCase
{
    public function test_it_resolves_store_from_direct_attribute(): void
    {
        $this->assertSame('array', CacheAttributeProvider::make(CachedPostsQuery::class)->store());
    }

    public function test_it_inherits_store_attribute_from_parent_class(): void
    {
        $this->assertSame('array', CacheAttributeProvider::make(InheritedCachedPostsQuery::class)->store());
    }

    public function test_it_resolves_store_from_config_attribute(): void
    {
        config()->set('services.sculptor.cache_store', 'database');

        $this->assertSame('database', CacheAttributeProvider::make(ConfigStoreCachedPostsQuery::class)->store());
    }
}
