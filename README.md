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
use Atldays\Sculptor\Attributes\CacheFor;
use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\Attributes\WithRelations;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultCollection;
use Illuminate\Contracts\Database\Eloquent\Builder;

#[CacheStore('redis')]
#[CacheFor(600)]
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
        $this->setCacheTags('products', "region:{$this->region}", "days:{$this->days}");
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
- `flushCacheTags(string|Stringable $tag, string|Stringable ...$tags)`
- `flushScope(...$arguments)`

Cache durations are measured in seconds and default to one hour. Use the class-level `#[CacheFor(600)]` attribute for a declarative default, or `setCacheFor()` for a runtime override. To execute a query without reading or writing cache entries, use `withoutCache()`. This also bypasses eager loaded relation caches in `BuilderCachedQuery`:

```php
$posts = CachedPublishedPostsQuery::make()
    ->withoutCache()
    ->effect();
```

This setting applies to that query instance and preserves its configured cache duration and existing entries. Call `withoutCache(false)` to restore caching for the instance. Changing the duration with `setCacheFor()` does not clear the explicit bypass setting.

A zero or negative duration in `#[CacheFor]` or passed to `setCacheFor()` also disables cache reads and writes. Setting a positive duration restores caching when `withoutCache()` is not active. Existing entries remain available until they expire or are invalidated.

Both cache strategies support every result trait, including cached `false`, `0`, and empty collections. Result-cache keys include the result operation as well as the query shape.

Queries with `lockForUpdate()`, `sharedLock()`, or a custom string lock bypass cache reads and writes. This also applies when a global scope or a `beforeQuery` callback adds the lock. Explicit `useWritePdo()` queries bypass the result cache as well. Existing entries are preserved. For database locks to cover subsequent work, execute the query inside the appropriate Laravel transaction.

`CachedQuery` uses one prepared builder for its cache key and execution. Global scopes and before-query callbacks run once per execution, including cache hits, so they can affect both the key and the decision to bypass caching.

The base cache tag contains the fully qualified query class name, for example `sculptor:App\Queries\Post\CachedPublishedPostsQuery`. Query classes with the same short name in different namespaces have independent base tags. Entries written with the previous short-name tag format remain under their original TTL and are no longer read by the updated query class.

### Cache Duration Attribute

Declare the default TTL on a query class:

```php
use App\Models\Post;
use Atldays\Sculptor\Attributes\CacheFor;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultAll;

#[ForModel(Post::class)]
#[CacheFor(600)]
class CachedPostsQuery extends CachedQuery
{
    use QueryResultAll;
}
```

Duration settings have the following priority, highest first:

1. `withoutCache(true)` disables reads and writes regardless of the configured TTL.
2. An explicit `setCacheFor(int|DateTime)` value overrides the attribute, including calls in constructors and an explicit `setCacheFor(3600)`.
3. The nearest `#[CacheFor]` class attribute supplies the default in seconds. A child inherits its parent's attribute unless it declares its own.
4. Without an attribute or runtime override, the TTL is `3600` seconds.

```php
$query = CachedPostsQuery::make();                  // 600 seconds
$query->setCacheFor(30);                            // 30 seconds
$query->withoutCache();                            // Bypass cache
$query->setCacheFor(new DateTime('+10 minutes'));   // Bypass remains active
$query->withoutCache(false);                       // Use that DateTime expiration
```

The attribute accepts an integer number of seconds, including zero and negative values to disable caching. A positive runtime override can enable a query whose attribute disables caching, provided `withoutCache()` is not active. Disabling and restoring the bypass preserves the latest runtime expiration or the attribute default. Remove constructor calls to `setCacheFor()` if the attribute should control that query's TTL.

Both `CachedQuery` and `BuilderCachedQuery` use this precedence, including builder-cached eager-loaded relations. `#[CacheFor]` works alongside `#[CacheScope]`, store attributes, manual tags, and both invalidation methods. TTL changes apply to subsequent writes; existing entries retain their original expiration until they expire or are invalidated.

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

To invalidate only a custom group, pass its tag explicitly:

```php
PaginatedPublishedPostsQuery::flushCacheTags('region:eu');
```

`getCache()` includes the base tag of the query class as well as its custom tags. Calling `getCache()->flush()` therefore invalidates the entire class and entries sharing any of those custom tags; it does not restrict invalidation to one region. Use `flushBaseCache()` to invalidate the class alone, or `flushCacheTags()` to invalidate explicitly selected groups.

### Invalidate Explicit Cache Tags

Assign domain tags when configuring queries, then use the same values when invalidating them:

```php
$query = UserProjectList::make(userId: 42)
    ->mergeCacheTags('projects:user:42');

$projects = $query->effect();

UserProjectList::flushCacheTags('projects:user:42');

UserProjectList::flushCacheTags(
    'projects:user:42',
    'projects:user:57',
);

AllProjectList::flushBaseCache(); // Invalidate the entire AllProjectList class.
```

`flushCacheTags()` invalidates entries carrying **any** supplied tag: multiple tags form a union, not an intersection. It does not add the calling class's base tag or any other implicit tag. Other owners' entries remain cached even when they share the class's base tag, unless they also carry one of the explicitly supplied tags.

The calling class selects the cache store through the existing package configuration and inherited `#[CacheStore]` / `#[CacheStoreFromConfig]` attributes. Invalidation is not limited to the calling class: a shared tag can invalidate multiple query classes, both cache strategies, and other Laravel cache entries using that tag in the same store namespace. Separately configured stores remain independent when their underlying cache namespaces are separate.

The method requires at least one `string` or `Stringable` argument. Each argument is converted to a string once, and all tags are validated before accessing the store. An empty string, including one produced by `Stringable`, throws `InvalidArgumentException` before any invalidation occurs. Values such as `'0'` and `' '` are valid; whitespace, case, and prefixes are preserved exactly.

The store's invalidation result is returned as a boolean, and store exceptions propagate to the caller. A store without cache-tag support throws Laravel's exception; Sculptor never falls back to flushing the entire store. The query is not instantiated, so static invalidation works with required constructor arguments and executes no query SQL.

`CachedQuery` and `BuilderCachedQuery` implement the optional `Atldays\Sculptor\Contracts\WithCacheTagInvalidation` interface, which extends `WithCache` and declares `flushCacheTags()`. Existing `WithCache`, `WithResultCache`, and `WithBuilderCache` contracts are unchanged. Classes composing `HasCache` directly can opt into the new interface explicitly.

Domain tag names and application helpers such as `flushForUser()` belong to your application. Sculptor does not infer tags from SQL or domain identifiers. `flushBaseCache()` keeps its existing behavior: invalidating all entries carrying the calling class's base tag.

### Automatic Cache Scopes

Use `#[CacheScope]` on promoted constructor parameters to group cached results without writing tag strings. All marked parameters form **one composite group**; unmarked parameters can affect the query without splitting that group.

```php
use App\Models\Project;
use Atldays\Sculptor\Attributes\CacheFor;
use Atldays\Sculptor\Attributes\CacheScope;
use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultAll;
use Illuminate\Contracts\Database\Eloquent\Builder;

#[ForModel(Project::class)]
#[CacheStore('redis')]
#[CacheFor(600)]
class UserProjectList extends CachedQuery
{
    use QueryResultAll;

    public function __construct(
        #[CacheScope] private readonly int $userId,
        private readonly ?string $status = null,
    ) {}

    public function query(): Builder
    {
        $query = parent::query()->where('user_id', $this->userId);
        if ($this->status !== null) {
            $query->where('status', $this->status);
        }

        return $query->orderByDesc('id');
    }
}

UserProjectList::make(userId: 42, status: 'active')->effect();
UserProjectList::make(userId: 42, status: 'archived')->effect();

// Both status variants are invalidated; user 57 remains cached.
UserProjectList::flushScope(userId: 42);
```

The generated tag includes the concrete query class and normalized marked values. It is appended to the query's manual tags and base tag. Result keys still distinguish SQL, bindings, projections, limits, and pagination settings; the scope groups their entries for invalidation. With `BuilderCachedQuery`, the scope also applies to cacheable eager-loaded relations. Both `new Query(...)` and `Query::make(...)` are supported.

Automatic scopes are class-local: two different query classes with identical arguments have different scope tags. Use manual shared domain tags with `flushCacheTags()` when invalidation should span query classes. `flushBaseCache()` still invalidates all scopes of the calling class. The generated tag format is internal and should not be constructed manually.

For a narrower group, mark all its identifying parameters:

```php
public function __construct(
    #[CacheScope] private readonly int $tenantId,
    #[CacheScope] private readonly int $userId,
    #[CacheScope] private readonly ?string $status = null,
) {}

UserProjectList::flushScope(tenantId: 7, userId: 42, status: 'active');
```

This matches the complete `(tenantId, userId, status)` tuple, rather than invalidating every matching tenant or user independently. Omitting `status` resolves its constructor default (`null`) and invalidates only that group; omitted optional values are not wildcards.

`flushScope()` accepts only marked parameters. Named arguments are recommended and may be passed in any order. Positional arguments follow the declaration order of the **marked** parameters, skipping unmarked constructor dependencies. Unknown parameters (including unmarked parameters), duplicate assignments, missing required values, and invalid types throw `InvalidArgumentException` before accessing the store. A class without a scope definition throws `LogicException` instead of falling back to class-wide invalidation.

Scope values support strings (including empty strings and `'0'`), integers, booleans, finite floats, `null`, unit or backed enum instances, Eloquent models, and objects implementing `CacheScopeKey`. Types are preserved: integer `42` and string `'42'` are distinct when both are allowed by the parameter type. Invalidation validates the declared parameter type without weak scalar coercion: pass `userId: 42` for an `int` parameter, not `userId: '42'`. Integer-to-float widening follows PHP's constructor behavior. Pass the enum instance, not its backing string. Arrays, arbitrary objects (including plain `Stringable` and services without `CacheScopeKey`), resources, `NAN`, and infinite floats are rejected. For other objects, implement `CacheScopeKey` or pass a stable scalar identifier. The same normalization is used for writing and invalidating entries.

Only promoted constructor parameters can be marked, so their values remain available without intercepting construction or resolving services. Inherited constructors retain their scopes, including private promoted properties. A replacement constructor must declare its own marked promoted parameters. Misconfigured or uninitialized scope properties throw `LogicException`.

`flushScope()` reads constructor metadata and supplied values without constructing the query or performing query SQL. It uses the configured cache store through `flushCacheTags()`, returning the store's boolean result and propagating its exceptions. The optional `Atldays\Sculptor\Contracts\WithCacheScopeInvalidation` contract extends `WithCacheTagInvalidation`; both cached presets implement it. Existing cache contracts remain unchanged. Queries without `#[CacheScope]` retain their previous tags and cache behavior.

### Scope Resolver

Internally, both cache strategies use the object-based `Atldays\Sculptor\Support\CacheScopeResolver`. Its factory accepts an existing query or a class name, following the `AttributeReader::make(...)` pattern:

```php
use Atldays\Sculptor\Support\CacheScopeResolver;

$query = UserProjectList::make(userId: 42);
$tag = CacheScopeResolver::make($query)->tag();

$tagFromArguments = CacheScopeResolver::make(UserProjectList::class)
    ->tagForArguments(['userId' => 42]);
```

The resolver retains constructor parameter metadata, reading current property values, model identities and custom keys on each resolution. It can be reused without retaining previous argument values or memoizing a tag. Creating a class-based resolver and calling `tagForArguments()` never constructs the query. `tag()` requires an object and returns `null` for queries without marked parameters; calling it on a class-only context throws `LogicException`.

The generated scope tags are identical to those produced by the previous static implementation, so `flushScope()` can still invalidate existing entries. The resolver remains an internal helper in `Support`: applications should normally use query methods such as `flushScope()` and `cacheTags()`.

### Attribute Reader

`Atldays\Sculptor\Support\AttributeReader` reads class attributes from an object or a class name. It returns the nearest matching attribute instance, searching parent classes when necessary, or `null` if the attribute is absent:

```php
use Atldays\Sculptor\Attributes\CacheFor;
use Atldays\Sculptor\Support\AttributeReader;

$attribute = AttributeReader::make(UserProjectList::class)->get(CacheFor::class);
$seconds = $attribute?->seconds();
```

The previous `Atldays\Sculptor\Attributes\Helpers\AttributeReader` namespace remains available as a deprecated compatibility class that inherits the implementation from `Support`. Its constructor and `make()` factory remain supported. Use the `Support` namespace in new code.

### Eloquent Model Scopes

An Eloquent model can be a marked constructor argument:

```php
public function __construct(
    #[CacheScope] private readonly User $user,
    private readonly ?string $status = null,
) {}

public function query(): Builder
{
    $query = parent::query()->where('user_id', $this->user->getKey());
    if ($this->status !== null) {
        $query->where('status', $this->status);
    }

    return $query->orderByDesc('id');
}

UserProjectList::make(user: $user)->effect();
UserProjectList::flushScope(user: $user);

// A different instance representing the same record identifies the same group.
$sameUser = clone $user;
UserProjectList::flushScope(user: $sameUser);
```

Model identity consists of the concrete model class, connection name, and the primary key returned by `getKey()` (including its integer/string type). An implicit connection uses the Eloquent resolver's current default connection name, matching a model with that connection set explicitly. No database connection is opened or record fetched by normalization. Different model classes, connection names, or keys identify different groups.

Non-key attributes, dirty values, loaded relations, and the `exists` flag do not participate in identity. Model objects and their full attribute arrays are not serialized into scope tags. A model with a null or non-integer/non-string key throws `InvalidArgumentException` before cache access; select its primary key when loading it. UUID/string keys and zero-valued keys are supported. Normalization does not verify record existence: keyed references and deleted model instances remain usable for invalidation.

For example, after deleting an owner and its projects, the retained owner instance can still invalidate its old group:

```php
UserProjectList::flushScope(user: $deletedUser);
```

Always pass a model matching the declared constructor type; an integer ID is not interchangeable with a model scope. Scope invalidation remains explicit: changing a model's attributes does not itself call `flushScope()`. If a model's primary key or connection changes, invalidate the old identity as well as the new one. In applications switching databases behind one connection name, include a separate tenant scope parameter or use a custom key containing that tenant identity.

### Custom Scope Keys for Data Objects

Implement `Atldays\Sculptor\Contracts\CacheScopeKey` to define a stable string component of a scope:

```php
use Atldays\Sculptor\Contracts\CacheScopeKey;

final readonly class ProjectScopeData implements CacheScopeKey
{
    public function __construct(
        public int $tenantId,
        public int $userId,
    ) {}

    public function cacheScopeKey(): string
    {
        return json_encode([$this->tenantId, $this->userId], JSON_THROW_ON_ERROR);
    }
}
```

Mark that data object as a constructor parameter:

```php
public function __construct(
    #[CacheScope] private readonly ProjectScopeData $context,
) {}

$query = UserProjectList::make(context: new ProjectScopeData(7, 42));
$projects = $query->effect();

UserProjectList::flushScope(context: new ProjectScopeData(7, 42));
```

The returned string is an identity component, not a literal Laravel cache tag. It is combined with the concrete query class and all other marked arguments, then hashed. It cannot be passed directly to `flushCacheTags()`. Scalar strings, models, enums, and custom components have separate type namespaces, so a custom key does not accidentally collide with an ordinary string argument.

For custom components, the object's class is deliberately not added to the key: implementations control that namespace themselves. Two DTO classes returning the same component can identify the same group within a query when the constructor type accepts both (for example, `CacheScopeKey`). Use explicit domain prefixes or unambiguous structured encoding when keys belong to different object types. Automatic groups remain query-class-local; shared Laravel tags still provide cross-query invalidation.

`CacheScopeKey` takes priority over built-in model or enum normalization when an object explicitly implements it. This lets an application define its own model identity policy. The contract also allows an empty string component, distinct from `null`; the final generated tag remains non-empty.

`cacheScopeKey()` must return a deterministic string based on the intended identity, with no SQL, service resolution, or mutable/time-dependent side effects. The method is evaluated during normalization for both caching and invalidation and may be evaluated more than once during query execution. Its exceptions propagate before cache invalidation. User-defined key methods and overridden model identity accessors are responsible for avoiding I/O; Sculptor does not load models or relations on their behalf.

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
