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
- Laravel `^12.72|^13.42` (Laravel 13 requires PHP `^8.3`)

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

Choose one result trait for each query class:

| Trait | Laravel operation | Result |
| --- | --- | --- |
| `QueryResultCollection` | `get()` with a required positive limit | Eloquent collection |
| `QueryResultAll` | `get()` with an optional limit | Eloquent collection |
| `QueryResultFirst` | `first()` | Model or `null` |
| `QueryResultFirstOrFail` | `firstOrFail()` | Model, or `ModelNotFoundException` |
| `QueryResultSole` | `sole()` | Exactly one model, or a native Laravel exception |
| `QueryResultExists` | `exists()` | `bool` |
| `QueryResultCount` | `count()` | `int` |
| `QueryResultValue` | `value($column)` | Column value or `null` |
| `QueryResultPluck` | `pluck($column, $key)` | Support collection of values |
| `QueryResultAggregate` | `sum`, `avg`, `min`, or `max` | Native scalar value or `null` |
| `QueryResultPaginated` | `paginate()` | Length-aware paginator |
| `QueryResultSimplePaginated` | `simplePaginate()` | Paginator without a total count |
| `QueryResultCursorPaginated` | `cursorPaginate()` | Cursor paginator |

All result traits work with `Query`, `CachedQuery`, and `BuilderCachedQuery`, and preserve Laravel's operation semantics. Existence checks and counts execute native scalar queries without hydrating models or eager loading relations.

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

### Collection Without a Required Limit

```php
use App\Models\User;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Concerns\QueryResultAll;
use Atldays\Sculptor\Query;

#[ForModel(User::class)]
class AllUsersQuery extends Query
{
    use QueryResultAll;
}

$users = AllUsersQuery::result();
$firstTen = AllUsersQuery::make()->limit(10)->effect();
```

`QueryResultAll` respects limits from `#[Limit]`, `limit()`, and custom `query()` implementations. Use `withoutLimit()` to disable a Sculptor attribute or runtime limit.

### Required Model and Scalar Results

```php
use App\Models\User;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCount;
use Atldays\Sculptor\Concerns\QueryResultExists;
use Atldays\Sculptor\Concerns\QueryResultFirstOrFail;

#[ForModel(User::class)]
class RequiredUserQuery extends CachedQuery
{
    use QueryResultFirstOrFail;
}

#[ForModel(User::class)]
class UsersExistQuery extends CachedQuery
{
    use QueryResultExists;
}

#[ForModel(User::class)]
class UserCountQuery extends CachedQuery
{
    use QueryResultCount;
}

$user = RequiredUserQuery::result();
$exists = UsersExistQuery::result();
$count = UserCountQuery::result();
```

Add conditions through filters or `query()` as with other query objects. `QueryResultFirstOrFail` preserves Laravel's `ModelNotFoundException`, including the model class. `QueryResultSole` uses `sole()`: zero rows throw `ModelNotFoundException`, and two or more rows throw `MultipleRecordsFoundException`. It checks up to two rows even if `limit(1)` is configured. `CachedQuery` does not store thrown exceptions. `BuilderCachedQuery` may cache the underlying empty or duplicate selection, so subsequent calls still throw until that entry expires or is invalidated.

### Column Values, Pluck, and Aggregates

Use `#[ResultColumn]` to configure the result column for these three traits. `#[PluckKey]` optionally provides collection keys, and `#[Aggregate]` specifies the aggregate function:

```php
use App\Models\Product;
use Atldays\Sculptor\Attributes\Aggregate;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\PluckKey;
use Atldays\Sculptor\Attributes\ResultColumn;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultAggregate;
use Atldays\Sculptor\Concerns\QueryResultPluck;
use Atldays\Sculptor\Concerns\QueryResultValue;
use Atldays\Sculptor\Enums\AggregateFunction;
use Atldays\Sculptor\Query;

#[ForModel(Product::class)]
#[ResultColumn('products.price')]
class FirstProductPriceQuery extends Query
{
    use QueryResultValue;
}

#[ForModel(Product::class)]
#[ResultColumn('products.name')]
#[PluckKey('products.id')]
class ProductNamesQuery extends CachedQuery
{
    use QueryResultPluck;
}

#[ForModel(Product::class)]
#[ResultColumn('products.price')]
#[Aggregate(AggregateFunction::Sum)]
class TotalProductPriceQuery extends CachedQuery
{
    use QueryResultAggregate;
}

$price = FirstProductPriceQuery::result();
$namesById = ProductNamesQuery::result();
$total = TotalProductPriceQuery::result();

$names = ProductNamesQuery::make()->withPluckKey(null)->limit(20)->effect();
$average = TotalProductPriceQuery::make()->withAggregateFunction('avg')->effect();
$largestId = TotalProductPriceQuery::make()
    ->withResultColumn('products.id')
    ->withAggregateFunction(AggregateFunction::Max)
    ->effect();
```

The attributes are inherited. Runtime overrides take precedence and apply to that instance:

- `withResultColumn(string|Expression $column)` overrides the column.
- `withPluckKey(string|Expression|null $key)` overrides the collection key; `null` explicitly disables an attribute-defined key.
- `withAggregateFunction(AggregateFunction|string $function)` accepts `sum`, `avg`, `min`, or `max`, case-insensitively.
- `resultColumn()`, `pluckKey()`, and `aggregateFunction()` return the effective configuration.

The result column is required. `QueryResultAggregate` also requires a function. Missing settings, blank column/key strings, and unsupported function names throw `InvalidArgumentException`.

For `Value` and `Pluck`, the result column and key define the effective `SELECT`, including when a query has `#[Select]`, `withSelect()`, or custom selections. Filters, joins, ordering, and limits remain in effect. Eager loading is disabled for these projections. Eloquent casts, accessors, and date conversion retain native Laravel behavior. Expressions and aliases follow the installed Laravel version's semantics.

Aggregates preserve Laravel's selection, `distinct`, grouping, `having`, and limit semantics. A grouped aggregate returns Laravel's scalar result. Results keep the database driver's types: exact decimal strings are preserved, with no additional conversion to `float`. Empty sums return Laravel's zero; empty averages, minima, and maxima return `null`.

All three traits support both cache strategies. Keys include the result column, optional pluck key, and aggregate function, so runtime overrides produce independent entries. `CachedQuery` caches `null` as a result, including a missing first record or a nullable column; it remains cached until expiration or invalidation. The existing cache bypass, tags, locking rules, and execution events apply to these traits as well.

### Execution API

- `::make(...$arguments)` resolves the query object through the container
- `->effect()` executes the query object
- `::result(...$arguments)` instantiates and executes in one call

### Query Execution Event

Every `::result()` call dispatches `Atldays\Sculptor\Events\ResultExecuted`.

This includes cache hits and calls that throw an exception. Calling `->effect()` directly does not dispatch this event.

The event contains:

- the query class name
- the execution start time
- the execution finish time
- the execution duration in milliseconds through `duration()`

## Pagination

Sculptor treats pagination as a separate result type, just like collections and single-model results.

Use:

- `QueryResultPaginated` for Laravel's `paginate()`
- `QueryResultSimplePaginated` for `simplePaginate()`, without a total count query
- `QueryResultCursorPaginated` for `cursorPaginate()`, without offsets or a total count query

Example:

```php
<?php

namespace App\Queries\Post;

use App\Models\Post;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Concerns\QueryResultPaginated;
use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Atldays\Sculptor\Query;

#[ForModel(Post::class)]
class PaginatedPostsQuery extends Query implements WithPaginatedResult
{
    use QueryResultPaginated;
}
```

`QueryResultPaginated` and `QueryResultSimplePaginated` require `WithPaginatedResult`.

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

If your query object also accepts constructor arguments, keep those arguments on `make()` and use the pagination helpers for paginator state:

```php
$posts = ProductsByRegionQuery::make(region: 'eu')
    ->perPage(15)
    ->page(2)
    ->effect();
```

When `page()` is not called, Laravel resolves the current page through its paginator resolver, which normally reads the current request query string. When `perPage()` is not called, Eloquent falls back to the model's paginator size through `getPerPage()`.

Pagination also works with `CachedQuery` and `BuilderCachedQuery` (with a `QueryCacheable` model). `CachedQuery` caches the paginator result: the resolved current page, effective page size, page name, columns, and base URL for pagination links are included in its key. Different pages and URLs are cached independently, including when Laravel resolves the page from the current request. Resolving request defaults does not change the query object's pagination settings. `BuilderCachedQuery` caches the underlying SQL results and creates a fresh paginator on each call, including its current link options.

Non-default `paginationColumns()` replaces the query's select list for that execution. The default `['*']` keeps the query's configured select list. This applies to all three pagination traits. Pagination uses Laravel's page-size semantics; `limit()` does not cap a page.

For cursor pagination, implement `WithCursorPaginatedResult`:

```php
use App\Models\Post;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCursorPaginated;
use Atldays\Sculptor\Contracts\WithCursorPaginatedResult;

#[ForModel(Post::class)]
class CursorPostsQuery extends CachedQuery implements WithCursorPaginatedResult
{
    use QueryResultCursorPaginated;
}

$page = CursorPostsQuery::make()
    ->perPage(15)
    ->cursorName('posts_cursor')
    ->cursor($encodedCursor) // Laravel Cursor instance, encoded string, or null
    ->effect();

$next = $page->nextCursor()?->encode();
```

When no cursor is configured (or `cursor(null)` is used), Laravel resolves it from the current request using `cursorName()`. Use `cursor('')` to explicitly select the first page. Invalid encoded strings follow Laravel's behavior and select the first page. The cursor's parameters and direction, page size, cursor name, columns, and base URL participate in the result cache key. A cursor object and its encoded string share the same entry. `cursorPaginationData()` exposes configured settings without resolving request defaults.

Use a stable, unique ordering for cursor pagination (for example, a timestamp followed by the primary key). Eloquent orders by the primary key when no ordering is supplied. Include every ordering column in the selected columns. Laravel's restrictions on nullable ordering columns, expressions, and cursor parameters still apply.

Paginator instances returned from result cache are cloned so changing their link options (`appends`, `fragment`, `withPath`) does not alter subsequent cache reads. Items retain the normal model/collection cache semantics. All new results support tags, `withoutCache()`, and non-positive TTL bypass. Locks and write-PDO reads bypass both cache strategies.

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
- `withoutCache(bool $withoutCache = true)`
- `setCacheTags(string ...$tags)`
- `mergeCacheTags(string ...$tags)`
- `cacheTags()`
- `cacheBaseTag()`
- `cache()`
- `cacheStore()`
- `getCache()`
- `flushBaseCache()`

Cache durations are measured in seconds and default to one hour. To execute a query without reading or writing cache entries, use `withoutCache()`. This also bypasses eager loaded relation caches in `BuilderCachedQuery`:

```php
$posts = CachedPublishedPostsQuery::make()
    ->withoutCache()
    ->effect();
```

This setting applies to that query instance and preserves its configured cache duration and existing entries. Call `withoutCache(false)` to restore caching for the instance. Changing the duration with `setCacheFor()` does not clear the explicit bypass setting.

A zero or negative duration passed to `setCacheFor()` also disables cache reads and writes. Setting a positive duration restores caching when `withoutCache()` is not active. Existing entries remain available until they expire or are invalidated.

Both cache strategies support every result trait, including cached `false`, `0`, and empty collections. Result-cache keys include the result operation as well as the query shape.

Queries with `lockForUpdate()`, `sharedLock()`, or a custom string lock bypass cache reads and writes. This also applies when a global scope or a `beforeQuery` callback adds the lock. Explicit `useWritePdo()` queries bypass the result cache as well. Existing entries are preserved. For database locks to cover subsequent work, execute the query inside the appropriate Laravel transaction.

`CachedQuery` uses one prepared builder for its cache key and execution. Global scopes and before-query callbacks run once per execution, including cache hits, so they can affect both the key and the decision to bypass caching.

The base cache tag contains the fully qualified query class name, for example `sculptor:App\Queries\Post\CachedPublishedPostsQuery`. Query classes with the same short name in different namespaces have independent base tags. Entries written with the previous short-name tag format remain under their original TTL and are no longer read by the updated query class.

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

The command returns exit code `0` when the cache is flushed successfully and `1` when the query class is invalid or the cache store fails to flush.

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

If you need custom composition, the package also exposes low-level building blocks:

- `HasQuery`
- `HasQueryWithFilters`
- `HasCache`
- `InteractsWithResultCache`
- `InteractsWithBuilderCache`
- `PreparesCacheableRelations`
- `HasResult`
- `QueryResultCollection`
- `QueryResultAll`
- `QueryResultFirst`
- `QueryResultFirstOrFail`
- `QueryResultSole`
- `QueryResultExists`
- `QueryResultCount`
- `QueryResultValue`
- `QueryResultPluck`
- `QueryResultAggregate`
- `QueryResultPaginated`
- `QueryResultSimplePaginated`
- `QueryResultCursorPaginated`

For most applications, the preset classes are the best starting point. Compose these traits only when the preset classes do not fit your query object.

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
