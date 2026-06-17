# Laravel Sculptor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/atldays/laravel-sculptor.svg?logo=packagist&style=for-the-badge)](https://packagist.org/packages/atldays/laravel-sculptor)
[![Total Downloads](https://img.shields.io/packagist/dt/atldays/laravel-sculptor.svg?style=for-the-badge&color=blue)](https://packagist.org/packages/atldays/laravel-sculptor)
[![CI](https://img.shields.io/github/actions/workflow/status/atldays/laravel-sculptor/ci.yml?style=for-the-badge&label=CI)](https://github.com/atldays/laravel-sculptor/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](LICENSE.md)

`laravel-sculptor` provides reusable query objects for Eloquent.

It moves complex read logic out of controllers and services into dedicated classes with a consistent API for:

- model binding
- `select` control
- eager loading
- filters
- result execution
- pagination
- result caching
- builder-level cache integration

## Why

As Laravel applications grow, read queries often become hard to maintain:

- controllers start building large queries inline
- services repeat the same `with`, `select`, and filtering logic
- cache behavior becomes inconsistent
- query rules become difficult to test in isolation

Sculptor solves this by introducing query objects: focused classes that represent a single read operation.

## Installation

Add the package via Composer:

```bash
composer require atldays/laravel-sculptor
```

Publish the package config if needed:

```bash
php artisan vendor:publish --tag=sculptor-config
```

## Requirements

- PHP `^8.2`
- Laravel `^10.0|^11.0|^12.0|^13.0`

## Core Classes

| Class | Filters | Cache | Requires query-cache-enabled model |
| --- | --- | --- | --- |
| `BaseQuery` | No | No | No |
| `Query` | Yes | No | No |
| `CachedQuery` | Yes | Result cache | No |
| `BuilderCachedQuery` | Yes | Builder cache | Yes |

These classes are the recommended entry points for day-to-day use. Lower-level traits are available when you need custom composition.

## Quick Start

```php
<?php

namespace App\Queries\Post;

use App\Models\Post;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\Attributes\WithRelations;
use Atldays\Sculptor\Concerns\QueryResultCollection;
use Atldays\Sculptor\Query;

#[ForModel(Post::class)]
#[WithRelations(['author'])]
#[Limit(10)]
class PublishedPostsQuery extends Query
{
    use QueryResultCollection;
}
```

Execute the query:

```php
$posts = PublishedPostsQuery::result();
```

Or create the object first and customize it before execution:

```php
$posts = PublishedPostsQuery::make()
    ->limit(5)
    ->withoutRelations()
    ->effect();
```

## Query Building

Every query object starts with a model. The recommended way to declare static query defaults is with class-level PHP attributes:

```php
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\Attributes\Select;
use Atldays\Sculptor\Attributes\WithRelations;

#[ForModel(Post::class)]
#[Select(['posts.id', 'posts.title', 'posts.author_id'])]
#[WithRelations(['author'])]
#[Limit(20)]
class PostIndexQuery extends Query
{
    use QueryResultCollection;
}
```

Sculptor supports:

- default `select(table.*)`
- custom `select`
- eager loading through relations
- overriding eager loads at runtime
- limiting result size

Runtime helpers:

- `withSelect(string|array $columns)`
- `select()`
- `withRelations(string|array|Collection $relations)`
- `relations()`
- `withoutRelations()`
- `resetRelations()`
- `limit(int $limit)`
- `hasLimit()`
- `withoutLimit()`
- `model()`
- `newModel()`

The existing extension points keep their precedence:

- runtime helpers such as `withSelect()`, `withRelations()`, and `limit()` override static defaults
- class properties such as `$model`, `$select`, and `$with` override attributes
- overriding `model()`, `select()`, `relations()`, or `query()` gives the query class full control
- `withoutLimit()` disables both a runtime limit and a `#[Limit]` default for that query instance

### Alternative Property Configuration

Properties remain fully supported for existing query objects and for teams that prefer PHP class state over attributes:

```php
<?php

namespace App\Queries\Post;

use App\Models\Post;
use Atldays\Sculptor\Concerns\QueryResultCollection;
use Atldays\Sculptor\Query;

class PostIndexQuery extends Query
{
    use QueryResultCollection;

    protected string $model = Post::class;

    protected array $select = [
        'posts.id',
        'posts.title',
        'posts.slug',
        'posts.author_id',
    ];

    protected array $with = [
        'author',
        'categories',
    ];

    public function __construct()
    {
        $this->limit(20);
    }
}
```

## Where To Put Query Logic

The main value of Sculptor is not simple one-line queries. It is the ability to move large, reusable read logic into dedicated classes.

As a rule of thumb:

- use attributes such as `#[ForModel]`, `#[Select]`, `#[WithRelations]`, and `#[Limit]` for static query structure
- use the constructor for runtime configuration such as filters, limits, cache tags, or arguments passed into the query object
- override `query()` when the query requires joins, subqueries, conditional clauses, aggregates, raw expressions, or other larger composition

For complex queries, start from the base implementation and then extend it:

```php
<?php

namespace App\Queries\Order;

use App\Models\Order;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\Attributes\WithRelations;
use Atldays\Sculptor\Concerns\QueryResultCollection;
use Atldays\Sculptor\Query;
use Illuminate\Contracts\Database\Eloquent\Builder;

#[ForModel(Order::class)]
#[WithRelations(['customer'])]
#[Limit(100)]
class OrdersReportQuery extends Query
{
    use QueryResultCollection;

    public function __construct(
        protected readonly ?int $customerId = null,
        protected readonly bool $onlyPaid = true,
    ) {}

    public function query(): Builder
    {
        $query = parent::query()
            ->leftJoin('payments', 'payments.order_id', '=', 'orders.id')
            ->leftJoin('shipments', 'shipments.order_id', '=', 'orders.id')
            ->addSelect([
                'orders.id',
                'orders.customer_id',
                'orders.total',
                'payments.status as payment_status',
                'shipments.tracking_number',
            ]);

        if ($this->customerId !== null) {
            $query->where('orders.customer_id', $this->customerId);
        }

        if ($this->onlyPaid) {
            $query->where('payments.status', 'paid');
        }

        return $query->orderByDesc('orders.created_at');
    }
}
```

This is the recommended pattern for larger query objects:

- keep reusable defaults in attributes
- keep runtime input in the constructor
- keep heavy SQL composition in `query()`
- return the final Eloquent builder from `query()`

That makes the query object easy to reuse, test, and evolve without pushing read logic back into controllers or services.

You can apply the same pattern to cached query objects:

```php
<?php

namespace App\Queries\Product;

use App\Models\Product;
use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\Attributes\WithRelations;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCollection;
use Illuminate\Contracts\Database\Eloquent\Builder;

#[CacheStore('redis')]
#[ForModel(Product::class)]
#[WithRelations(['brand', 'categories'])]
#[Limit(20)]
class TrendingProductsQuery extends CachedQuery
{
    use QueryResultCollection;

    public function __construct(
        protected readonly string $region,
        protected readonly int $days = 7,
    ) {
        $this
            ->setCacheFor(600)
            ->setCacheTags('products', "region:{$this->region}", "days:{$this->days}");
    }

    public function query(): Builder
    {
        return parent::query()
            ->join('product_stats', 'product_stats.product_id', '=', 'products.id')
            ->where('product_stats.region', $this->region)
            ->where('product_stats.period_days', $this->days)
            ->where('products.is_active', true)
            ->orderByDesc('product_stats.score')
            ->addSelect([
                'products.id',
                'products.name',
                'products.slug',
                'products.brand_id',
                'product_stats.score',
            ]);
    }
}
```

This gives you a single reusable class that owns:

- the query shape
- the runtime inputs
- the cache policy
- the cache tags used for later invalidation

## Filters

`Query` includes filter support out of the box through `atldays/laravel-eloquent-filters`.

```php
<?php

namespace App\Queries\Post;

use App\Models\Post;
use App\QueryFilters\PublishedFilter;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\Concerns\QueryResultCollection;
use Atldays\Sculptor\Query;

#[ForModel(Post::class)]
#[Limit(10)]
class PublishedPostsQuery extends Query
{
    use QueryResultCollection;

    public function __construct()
    {
        $this->addFilter(new PublishedFilter());
    }
}
```

If you do not want filter integration, use `BaseQuery`.

## Result Execution

Sculptor provides three execution helpers:

- `QueryResultCollection` for `get()`
- `QueryResultFirst` for `first()`
- `QueryResultPaginated` for `paginate()`

### Collection Result

```php
use App\Models\User;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Limit;

#[ForModel(User::class)]
#[Limit(25)]
class UsersQuery extends Query
{
    use QueryResultCollection;
}
```

### First Result

```php
use App\Models\User;
use Atldays\Sculptor\Attributes\ForModel;

#[ForModel(User::class)]
class FirstUserQuery extends Query
{
    use QueryResultFirst;
}
```

### Execution API

- `::make(...$arguments)` resolves the query object through the container
- `->effect()` executes the query object
- `::result(...$arguments)` instantiates and executes in one call

### Query Execution Event

Every `::result()` call dispatches `Atldays\Sculptor\Events\ResultExecuted`.

The event contains:

- the query class name
- the execution start time
- the execution finish time
- the execution duration in milliseconds through `duration()`

## Pagination

Sculptor treats pagination as a separate result type, just like collections and single-model results.

Use:

- `QueryResultPaginated` for Laravel's `paginate()`

Example:

```php
<?php

namespace App\Queries\Post;

use App\Models\Post;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Concerns\QueryResultPaginated;
use Atldays\Sculptor\Query;

#[ForModel(Post::class)]
class PaginatedPostsQuery extends Query
{
    use QueryResultPaginated;
}
```

Runtime pagination helpers:

- `perPage(int $perPage)`
- `page(int $page)`
- `pageName(string $pageName)`
- `paginationColumns(string|array $columns)`

Usage:

```php
$posts = PaginatedPostsQuery::make()
    ->perPage(15)
    ->page(2)
    ->effect();
```

Or use the static shortcut:

```php
$posts = PaginatedPostsQuery::paginate(perPage: 15, page: 2);
```

If your query object also accepts constructor arguments, pass them as additional named arguments:

```php
$posts = ProductsByRegionQuery::paginate(
    perPage: 15,
    page: 2,
    region: 'eu',
);
```

Pagination also works with `CachedQuery`, and the pagination state is included in the cache key so different pages are cached independently.

Use the dedicated pagination methods for page state. Constructor arguments should represent business input such as tenant, region, status, or date range, not paginator state.

## Caching

Sculptor supports two cache strategies.

### CachedQuery

`CachedQuery` caches the result of the query object through Laravel cache.

It does not require a custom Eloquent query builder or a special trait on the model.

```php
<?php

namespace App\Queries\Post;

use App\Models\Post;
use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\Attributes\WithRelations;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCollection;

#[CacheStore('array')]
#[ForModel(Post::class)]
#[WithRelations(['author'])]
#[Limit(10)]
class CachedPublishedPostsQuery extends CachedQuery
{
    use QueryResultCollection;

    public function __construct()
    {
        $this
            ->setCacheFor(300)
            ->setCacheTags('posts', 'published');
    }
}
```

Use this when you want simple query-object-level caching and do not need deep builder integration.

### BuilderCachedQuery

`BuilderCachedQuery` integrates with `atldays/laravel-eloquent-query-cache`.

Use this only when your model already uses the query-cache package integration and returns a cache-aware query builder.

```php
<?php

namespace App\Queries\Post;

use App\Models\Post;
use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\Attributes\WithRelations;
use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCollection;

#[CacheStore('redis')]
#[ForModel(Post::class)]
#[WithRelations(['author'])]
#[Limit(10)]
class BuilderCachedPostsQuery extends BuilderCachedQuery
{
    use QueryResultCollection;

    public function __construct()
    {
        $this
            ->setCacheFor(300)
            ->setCacheTags('posts');
    }
}
```

If the model is not configured for the query-cache package, this mode fails intentionally.

## Cache Configuration

Available cache helpers:

- `setCacheFor(int|DateTime $time)`
- `setCacheTags(string ...$tags)`
- `mergeCacheTags(string ...$tags)`
- `cacheTags()`
- `cacheBaseTag()`
- `cache()`
- `cacheStore()`
- `getCache()`
- `flushBaseCache()`

### Custom Cache Tags

Custom tags are useful when the same query class can produce multiple cached result groups.

For example, when a query depends on constructor arguments such as tenant, status, region, or another runtime scope, you can attach additional tags and later invalidate that group explicitly.

```php
<?php

namespace App\Queries\Post;

use App\Models\Post;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCollection;

#[ForModel(Post::class)]
#[Limit(10)]
class PaginatedPublishedPostsQuery extends CachedQuery
{
    use QueryResultCollection;

    public function __construct(public readonly string $region)
    {
        $this
            ->setCacheFor(300)
            ->setCacheTags('posts', "region:{$this->region}");
    }
}
```

You can also add tags incrementally:

```php
$query = PaginatedPublishedPostsQuery::make(region: 'eu')
    ->mergeCacheTags('tenant:acme');
```

Because `getCache()` returns the tagged Laravel cache repository for the query class, you can invalidate a custom group directly:

```php
PaginatedPublishedPostsQuery::make(region: 'eu')
    ->getCache()
    ->flush();
```

The final tag set always includes the base tag for the query class, so class-level invalidation through `flushBaseCache()` remains available.

### Cache Store Attributes

```php
use Atldays\Sculptor\Attributes\CacheStore;

#[CacheStore('redis')]
class CachedUsersQuery extends CachedQuery
{
    // ...
}
```

Or resolve the store from configuration:

```php
use Atldays\Sculptor\Attributes\CacheStoreFromConfig;

#[CacheStoreFromConfig('services.cache.queries_store', 'redis')]
class CachedUsersQuery extends CachedQuery
{
    // ...
}
```

### Flush Query Cache

Flush the base cache for a query class:

```php
CachedPublishedPostsQuery::flushBaseCache();
```

Or through Artisan:

```bash
php artisan sculptor:flush-cache "App\\Queries\\Post\\CachedPublishedPostsQuery"
```

## Choosing the Right Class

Use `BaseQuery` when:

- you only want a plain query object
- you do not need filters
- you do not need cache

Use `Query` when:

- you want the default query object experience
- you want filter support
- you do not need cache

Use `CachedQuery` when:

- you want filter support
- you want cache
- you do not want to depend on a cache-aware Eloquent builder

Use `BuilderCachedQuery` when:

- you want filter support
- you already use `atldays/laravel-eloquent-query-cache`
- you want builder-level cache integration for the query and eager loaded relations

## Traits

If you need custom composition, the package also exposes low-level and capability traits:

- `HasQuery`
- `HasQueryWithFilters`
- `HasQueryWithCache`
- `HasQueryWithBuilderCache`
- `HasQueryWithFiltersAndCache`
- `HasQueryWithFiltersAndBuilderCache`
- `HasCache`
- `HasResult`
- `QueryResultCollection`
- `QueryResultFirst`

For most applications, the preset classes are the best starting point.

## Testing

Query objects are straightforward to test because they isolate read logic in dedicated classes.

This package is tested across:

- multiple Laravel versions
- multiple PHP versions
- plain queries
- filters
- result cache
- builder cache integration

## License

MIT
