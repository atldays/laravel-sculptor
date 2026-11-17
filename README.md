# Laravel Sculptor

**A home for complex, reusable Eloquent queries.**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/atldays/laravel-sculptor.svg?logo=packagist&style=for-the-badge)](https://packagist.org/packages/atldays/laravel-sculptor)
[![Total Downloads](https://img.shields.io/packagist/dt/atldays/laravel-sculptor.svg?style=for-the-badge&color=blue)](https://packagist.org/packages/atldays/laravel-sculptor)
[![CI](https://img.shields.io/github/actions/workflow/status/atldays/laravel-sculptor/ci.yml?style=for-the-badge&label=CI)](https://github.com/atldays/laravel-sculptor/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](LICENSE.md)

Sculptor keeps relationship constraints, joins, filters, projections, and ordering in dedicated query classes. Reuse those classes from controllers, jobs, commands, or services, with caching and invalidation defined alongside the read logic.

- **Encapsulate complex reads.** Give a substantial query a name, explicit constructor inputs, and a place to evolve and test.
- **Keep cache behavior with the query.** Choose result or builder caching, declare a TTL, and bypass caching when you need a fresh read.
- **Invalidate by domain inputs.** Clear all of a user's cached pages or a subset of a report through constructor-argument scopes, or share tags across queries.
- **Choose the result type.** Use 13 result traits for models, collections, scalar values, aggregates, and pagination, with attributes and fluent overrides for configuration.

## Installation

```bash
composer require atldays/laravel-sculptor
```

Requires PHP `^8.2` and Laravel `^12.72|^13.42`. Laravel 13 requires PHP `^8.3`. The service provider is discovered automatically.

## Quick Start

Build a user's published-post list with approved comments, author loading, comment counts, optional title search, and ordering by discussion activity. The query class owns those rules; callers supply the inputs:

```php
<?php

namespace App\Queries\Post;

use App\Models\Post;
use Atldays\Sculptor\Attributes\{ForModel, Select, WithRelations};
use Atldays\Sculptor\Concerns\QueryResultAll;
use Atldays\Sculptor\Query;
use Illuminate\Contracts\Database\Eloquent\Builder;

#[ForModel(Post::class)]
#[Select(['posts.id', 'posts.author_id', 'posts.title'])]
#[WithRelations(['author'])]
class PostsForUser extends Query
{
    use QueryResultAll;

    public function __construct(
        private readonly int $userId,
        private readonly ?string $search = null,
    ) {}

    public function query(): Builder
    {
        $approvedComments = fn (Builder $comments) => $comments->where('approved', true);

        $query = parent::query()
            ->where('posts.author_id', $this->userId)
            ->where('posts.published', true)
            ->whereHas('comments', $approvedComments)
            ->withCount(['comments as approved_comments_count' => $approvedComments]);

        if ($this->search !== null) {
            $query->where('posts.title', 'like', '%' . $this->search . '%');
        }

        return $query
            ->orderByDesc('approved_comments_count')
            ->orderByDesc('posts.id');
    }
}
```

Execute it directly, or customize an instance before execution:

```php
use App\Queries\Post\PostsForUser;

// Get the result immediately using the query's defaults.
$posts = PostsForUser::result(userId: 42);

// Or customize this execution before retrieving the result.
$preview = PostsForUser::make(userId: 42, search: 'Laravel')
    ->limit(5)
    ->withoutRelations()
    ->effect();
```

The example assumes `Post` has `author` and `comments` relationships, and comments have an `approved` flag.

`QueryResultAll` returns an Eloquent collection through `get()`. It works without a limit and respects one when you set it. Include the primary and foreign keys needed by your eager-loaded relations when selecting specific columns.

`make()` resolves the query through Laravel's container, so constructor dependencies can be injected alongside named input arguments. `result()` creates and executes an instance; `effect()` executes an instance you have configured. Use one result trait per query class.

## Choose Your Result

All result traits live in `Atldays\Sculptor\Concerns`. Choose the operation that describes your read; each trait delegates to the corresponding Laravel method.

| Trait | Laravel operation | Result / purpose |
| --- | --- | --- |
| `QueryResultAll` | `get()` | Eloquent collection; optional limit |
| `QueryResultCollection` | `get()` | Eloquent collection with a required positive Sculptor limit |
| `QueryResultFirst` | `first()` | First model or `null` |
| `QueryResultFirstOrFail` | `firstOrFail()` | First model or `ModelNotFoundException` |
| `QueryResultSole` | `sole()` | Exactly one model; throws when zero or multiple records match |
| `QueryResultExists` | `exists()` | `bool`, without loading models |
| `QueryResultCount` | `count()` | `int` |
| `QueryResultValue` | `value()` | One column's value or `null` |
| `QueryResultPluck` | `pluck()` | Support collection of values, optionally keyed by another column |
| `QueryResultAggregate` | `sum()`, `avg()`, `min()`, `max()` | Native aggregate value |
| `QueryResultPaginated` | `paginate()` | `LengthAwarePaginator`, including a total count |
| `QueryResultSimplePaginated` | `simplePaginate()` | `Paginator`, without a total count query |
| `QueryResultCursorPaginated` | `cursorPaginate()` | `CursorPaginator` for cursor navigation |

### Models and Checks

A model result can still represent a substantial read. For example, choose the next post to moderate by finding an author's published posts with pending comments and prioritizing the largest queue:

```php
use App\Models\Post;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Concerns\QueryResultFirstOrFail;
use Atldays\Sculptor\Query;
use Illuminate\Contracts\Database\Eloquent\Builder;

#[ForModel(Post::class)]
class NextPostToModerate extends Query
{
    use QueryResultFirstOrFail;

    public function __construct(private readonly int $userId) {}

    public function query(): Builder
    {
        $pendingComments = fn (Builder $comments) => $comments->where('approved', false);

        return parent::query()
            ->where('posts.author_id', $this->userId)
            ->where('posts.published', true)
            ->whereHas('comments', $pendingComments)
            ->withCount(['comments as pending_comments_count' => $pendingComments])
            ->orderByDesc('pending_comments_count')
            ->orderBy('posts.id');
    }
}

$post = NextPostToModerate::result(userId: 42);
```

Use `QueryResultFirstOrFail` when the workflow requires a match, `QueryResultFirst` when absence is expected, or `QueryResultSole` when multiple matches must also be an error. `sole()` preserves Laravel's `ModelNotFoundException` and `MultipleRecordsFoundException`.

For checks and counts, pair the same query-building approach with `QueryResultExists` or `QueryResultCount`. These perform database operations for the requested result rather than fetching a collection to inspect in PHP.

### Column Values, Keyed Lists, and Aggregates

For a tenant's paid-order report over a date range, an abstract query base can own the shared read rules. Concrete classes choose how those rules return their result:

```php
namespace App\Queries\Order;

use App\Models\Order;
use Atldays\Sculptor\Attributes\{Aggregate, ForModel, PluckKey, ResultColumn};
use Atldays\Sculptor\Concerns\{QueryResultAggregate, QueryResultPluck, QueryResultValue};
use Atldays\Sculptor\Enums\AggregateFunction;
use Atldays\Sculptor\Query;
use Illuminate\Contracts\Database\Eloquent\Builder;

#[ForModel(Order::class)]
abstract class PaidOrders extends Query
{
    public function __construct(
        private readonly int $tenantId,
        private readonly string $from,
        private readonly string $until,
    ) {}

    public function query(): Builder
    {
        return parent::query()
            ->where('orders.tenant_id', $this->tenantId)
            ->where('orders.status', 'paid')
            ->where('orders.paid_at', '>=', $this->from)
            ->where('orders.paid_at', '<', $this->until)
            ->orderBy('orders.id');
    }
}

#[ResultColumn('orders.total')]
class OrderTotal extends PaidOrders
{
    use QueryResultValue;
}

#[ResultColumn('orders.total')]
#[PluckKey('orders.id')]
class OrderTotalList extends PaidOrders
{
    use QueryResultPluck;
}

#[ResultColumn('orders.total')]
#[Aggregate(AggregateFunction::Sum)]
class OrderRevenue extends PaidOrders
{
    use QueryResultAggregate;
}

$period = ['tenantId' => 7, 'from' => '2026-11-01', 'until' => '2026-12-01'];

$firstOrderTotal = OrderTotal::result(...$period);
$totalsById = OrderTotalList::result(...$period);
$totalRevenue = OrderRevenue::result(...$period);

$customerIds = OrderTotalList::make(...$period)
    ->withResultColumn('orders.customer_id')
    ->withPluckKey(null)
    ->effect();

$averageOrderTotal = OrderRevenue::make(...$period)
    ->withAggregateFunction(AggregateFunction::Avg)
    ->effect();
```

The date range includes `from` and excludes `until`. The same tenant, payment-status, and date constraints apply to every result above. `withPluckKey(null)` explicitly removes an attribute-defined key. Column and key overrides also accept Laravel SQL expressions. Aggregate operations accept `AggregateFunction` cases or strings such as `'sum'` and `'avg'`.

Value and pluck queries select their projection instead of `#[Select]` / `withSelect()` columns and skip eager loading. They preserve Laravel's casts, accessors, and date handling. Aggregates preserve native grouping, `having`, and database return types: decimal strings are not forcibly converted to floats. Empty `sum()` returns zero; empty `avg()`, `min()`, and `max()` return `null`, following Laravel.

### Deliberately Bounded Collections

Use `QueryResultCollection` when forgetting a limit should be an error. A bounded preview can reuse the same paid-order report rules:

```php
namespace App\Queries\Order;

use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\Concerns\QueryResultCollection;

#[Limit(10)]
class PaidOrderPreview extends PaidOrders
{
    use QueryResultCollection;
}

$orders = PaidOrderPreview::make(...$period)->limit(20)->effect();
```

Set the positive limit through `#[Limit]` or Sculptor's `limit()` method. A limit added only inside an overridden `query()` does not satisfy this guard. Choose `QueryResultAll` for intentionally unbounded collections.

## Configure with PHP Attributes

Attributes live in `Atldays\Sculptor\Attributes`. They keep a query's defaults beside its definition; runtime methods let callers customize an instance.

| Attribute | Purpose | Runtime configuration / related API |
| --- | --- | --- |
| `#[ForModel(Post::class)]` | Bind an Eloquent model | `model()`, `newModel()` |
| `#[Select(['posts.id', 'posts.title'])]` | Default selected columns | `withSelect(...)`, `select()` |
| `#[WithRelations(['author'])]` | Default eager-loaded relations | `withRelations(...)`, `withoutRelations()`, `resetRelations()` |
| `#[Limit(10)]` | Default result limit | `limit(20)`, `withoutLimit()`, `hasLimit()` |
| `#[ResultColumn('posts.title')]` | Value, pluck, or aggregate column | `withResultColumn(...)` |
| `#[PluckKey('posts.id')]` | Optional key for a pluck collection | `withPluckKey(...)` |
| `#[Aggregate(AggregateFunction::Sum)]` | Aggregate operation | `withAggregateFunction(...)` |
| `#[CacheFor(600)]` | Default cache TTL in seconds | `setCacheFor(...)`, `withoutCache()` |
| `#[CacheScope]` | Group entries by a promoted constructor argument | `flushScope(...)` |
| `#[CacheStore('redis')]` | Select a cache store | `cacheStore()` |
| `#[CacheStoreFromConfig('services.cache.queries_store', 'redis')]` | Resolve a store from config, with a fallback | `cacheStore()` |
| `#[DefaultCacheStore]` | Use Sculptor's configured default store | `cacheStore()` |

`AggregateFunction` lives in `Atldays\Sculptor\Enums`. All attributes are class-level except `CacheScope`, which marks promoted constructor parameters. Class attributes are inherited; the nearest matching declaration wins. `DefaultCacheStore` can explicitly restore default-store selection in a subclass.

Runtime overrides take precedence over attribute defaults. The existing `$model`, `$select`, and `$with` properties remain supported; those properties take precedence over their corresponding attributes, after applicable runtime overrides. With no select configuration, Sculptor selects the model's qualified `*` columns.

```php
$posts = PostsForUser::make(userId: 42)
    ->withSelect(['posts.id', 'posts.author_id', 'posts.title', 'posts.created_at'])
    ->withRelations(['author'])
    ->limit(25)
    ->effect();
```

`withSelect()` and `withRelations()` replace their respective defaults for that instance. `resetRelations()` restores the default relation configuration; `withoutRelations()` disables eager loading. `withoutLimit()` removes the configured bound, including an attribute default.

## Organize Read Logic

Choose a base class from `Atldays\Sculptor` according to how your query will be used:

- **`Query`** is the usual starting point, combining query construction with composable filters.
- **`CachedQuery`** adds caching for the completed result and works with standard Eloquent models.
- **`BuilderCachedQuery`** caches the underlying database queries and cacheable eager-loaded relations. Use it with models that include `QueryCacheable`.
- **`BaseQuery`** provides query construction alone, for reads that do not need filter integration or Sculptor caching.

The three filter-enabled classes require the model's `Filterable` trait when applying filters. Keep application-specific rules in `query()`; calling `parent::query()` preserves the model, selected columns, relations, filters, and configured cache strategy.

Group query classes by the feature they serve. For example:

```text
app/
├── Models/
│   ├── Order.php
│   └── Post.php
└── Queries/
    ├── Order/
    │   ├── PaidOrders.php
    │   ├── OrderTotal.php
    │   ├── OrderTotalList.php
    │   ├── OrderRevenue.php
    │   └── PaidOrderPreview.php
    └── Post/
        ├── Filters/
        │   └── PublishedFilter.php
        ├── PostsForUser.php
        ├── NextPostToModerate.php
        └── PaginatedPostsForUser.php
```

A controller, job, or command can call the same `PostsForUser::result(userId: 42)`. Constructor arguments carry inputs such as user IDs and date ranges; the class owns how those inputs shape the read. Keep feature-specific filters alongside their query classes, such as `Queries/Post/Filters`. Queries shared across application entry points stay in this feature directory as their rules grow.

### Compose Filters

`Query`, `CachedQuery`, and `BuilderCachedQuery` integrate with [Laravel Eloquent Filters](https://github.com/atldays/laravel-eloquent-filters). Add its `Atldays\EloquentFilters\Filterable` trait to models that use filters, then implement reusable rules:

```php
namespace App\Queries\Post\Filters;

use Atldays\EloquentFilters\Contracts\EloquentFilterContract;
use Illuminate\Database\Eloquent\Builder;

class PublishedFilter implements EloquentFilterContract
{
    public function apply(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function isApplicable(): bool
    {
        return true;
    }
}
```

Apply filters at the call site, or add defaults in the query's constructor. For example, add a publication filter to the paginated query shown below:

```php
use App\Queries\Post\{Filters\PublishedFilter, PaginatedPostsForUser};

$posts = PaginatedPostsForUser::make(userId: 42)
    ->addFilter(new PublishedFilter)
    ->effect();
```

## Pagination

Choose the result trait for your navigation style and implement its matching contract:

| Trait | Required contract (`Atldays\Sculptor\Contracts`) | Best fit |
| --- | --- | --- |
| `QueryResultPaginated` | `WithPaginatedResult` | Numbered pages with totals |
| `QueryResultSimplePaginated` | `WithPaginatedResult` | Previous / next navigation |
| `QueryResultCursorPaginated` | `WithCursorPaginatedResult` | Cursor-based feeds |

Keep the complete read and its cache policy together. This paginated post list loads authors, counts approved comments, filters by owner and status, and caches the result for ten minutes:

```php
<?php

namespace App\Queries\Post;

use App\Models\Post;
use Atldays\Sculptor\Attributes\{CacheFor, CacheScope, CacheStore, ForModel, WithRelations};
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultPaginated;
use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Illuminate\Contracts\Database\Eloquent\Builder;

#[ForModel(Post::class)]
#[CacheStore('redis')]
#[CacheFor(600)]
#[WithRelations(['author'])]
class PaginatedPostsForUser extends CachedQuery implements WithPaginatedResult
{
    use QueryResultPaginated;

    public function __construct(
        #[CacheScope] private readonly int $userId,
        #[CacheScope] private readonly ?string $status = null,
    ) {}

    public function query(): Builder
    {
        $query = parent::query()
            ->where('posts.author_id', $this->userId)
            ->withCount([
                'comments as approved_comments_count' => fn (Builder $comments) => $comments->where('approved', true),
            ]);

        if ($this->status !== null) {
            $query->where('posts.status', $this->status);
        }

        return $query
            ->orderByDesc('approved_comments_count')
            ->orderByDesc('posts.id');
    }
}
```

This example also assumes a `posts.status` column and a configured Redis cache store. Use `Query` instead of `CachedQuery` and omit the cache attributes when caching is unnecessary.

```php
use App\Queries\Post\PaginatedPostsForUser;

// Resolve ?page=... from the current request and use the model's page size.
$posts = PaginatedPostsForUser::result(userId: 42, status: 'published');

// Or supply pagination explicitly, including a custom query-string parameter.
$posts = PaginatedPostsForUser::make(userId: 42, status: 'published')
    ->perPage(20)
    ->page(2)
    ->pageName('posts_page')
    ->effect();

// Invalidate every status and every cached page for this user.
PaginatedPostsForUser::flushScope(userId: 42);
```

`perPage()`, `page()`, `pageName()`, and `paginationColumns()` are available for both numbered pagination traits. `paginationData()` returns the configured settings. The default `paginationColumns(['*'])` preserves `#[Select]` / `withSelect()`; an explicit column list replaces the execution select. Page size comes from `perPage()` or the model's `getPerPage()`, not `#[Limit]`.

Result-cache keys distinguish the resolved page, page size, page name, selected columns, and pagination path. Request-derived page numbers are resolved before key generation, so pages do not share an entry accidentally. Builder caching reuses the underlying SQL results and builds a fresh paginator for each call.

### Cursor Navigation

Use a stable, unique ordering and include the ordered columns in the selection:

```php
use App\Models\Post;
use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Concerns\QueryResultCursorPaginated;
use Atldays\Sculptor\Contracts\WithCursorPaginatedResult;
use Atldays\Sculptor\Query;
use Illuminate\Contracts\Database\Eloquent\Builder;

#[ForModel(Post::class)]
class PostFeed extends Query implements WithCursorPaginatedResult
{
    use QueryResultCursorPaginated;

    public function query(): Builder
    {
        return parent::query()
            ->where('posts.published', true)
            ->whereHas('comments', fn (Builder $comments) => $comments->where('approved', true))
            ->orderByDesc('posts.id');
    }
}

$firstPage = PostFeed::make()->perPage(20)->cursor('')->effect();

if (($nextCursor = $firstPage->nextCursor()) !== null) {
    $nextPage = PostFeed::make()->perPage(20)->cursor($nextCursor)->effect();
}
```

Cursor queries provide `perPage()`, `cursor(Cursor|string|null)`, `cursorName()`, `paginationColumns()`, and `cursorPaginationData()`. With no explicit cursor, or with `null`, Laravel resolves it from the request; `cursor('')` explicitly starts at the first page. Encoded strings and their equivalent `Cursor` objects share the same cache identity, including navigation direction. For caching, the same trait works with either cached preset.

## Caching

Choose a strategy according to what you want to reuse. Both support all 13 result traits, TTL attributes, runtime bypass, manual tags, and automatic cache scopes.

| Strategy | What is cached | When to choose it |
| --- | --- | --- |
| `CachedQuery` | The completed model, collection, scalar, or paginator result | Cache a query object without changing its Eloquent models |
| `BuilderCachedQuery` | Database query results, with cacheable eager-loaded relations | Integrate with [Laravel Eloquent Query Cache](https://github.com/atldays/laravel-eloquent-query-cache) |

For builder caching, add `Atldays\QueryCache\Traits\QueryCacheable` to the queried model and to related models whose queries should be cached. The preset validates support on the queried model. To switch the pagination example to builder caching, extend `BuilderCachedQuery`; keep the same result trait, attributes, and invalidation calls. Result caching works with ordinary Eloquent models. If a model independently enables Query Cache through its own defaults, that cache remains a separate layer outside transaction bypass.

Sculptor requires Laravel Eloquent Query Cache `^2.1.1`. Polymorphic eager loads inherit the relation's cache settings as defaults; per-type `constrain()` callbacks preserve their filters, projections, and cache overrides.

Use a **tag-capable cache store**, such as Redis. Unsupported stores throw; Sculptor never falls back to clearing the entire store. Publish configuration when you need to change the package default:

```bash
php artisan vendor:publish --tag=sculptor-config
```

Set `SCULPTOR_CACHE_STORE=redis` to select the package default. The published `sculptor.cache_store` setting falls back to `CACHE_STORE` / `CACHE_DRIVER` when that variable is absent. Class-level store attributes can select a different store for a query. Inspect its effective configuration with `cacheFor()`, `cacheStore()`, `cacheTags()`, and the static `cacheBaseTag()`.

### Duration and Bypass

`#[CacheFor(600)]` defines a ten-minute default. Runtime settings apply to that instance:

```php
$query = PaginatedPostsForUser::make(userId: 42);

$posts = $query->setCacheFor(30)->effect();
$freshPosts = $query->withoutCache()->effect();
$cachedPosts = $query->withoutCache(false)->effect();
```

Priority is: `withoutCache(true)` → explicit `setCacheFor(int|DateTime)` → nearest `#[CacheFor]` → `3600` seconds. Zero or negative TTL also bypasses reads and writes. A positive TTL restores caching unless the explicit bypass is still active. Changing TTL does not clear `withoutCache()`.

Bypass preserves existing entries and the configured TTL, including eager-loaded relation cache settings in builder mode. TTL changes affect subsequent writes; existing entries retain their original expiration. Locked queries (`lockForUpdate()`, `sharedLock()`, or custom string locks) and explicit `useWritePdo()` reads bypass caching. Use a transaction when database locks must protect subsequent work.

Both strategies cache `null`, `false`, zero, and empty collections. Native lookup exceptions propagate and are not cached. Keys distinguish result operations and projection parameters, so value, pluck, and aggregate reads do not collide.

### Bypass Cache Inside Transactions

Implement `Atldays\Sculptor\Contracts\WithoutCacheInTransactions` on a `CachedQuery` or `BuilderCachedQuery` when its reads must go to the database during a transaction. The marker is optional; query classes without it keep their existing behavior.

For example, reuse the paginated query above with this policy:

```php
namespace App\Queries\Post;

use Atldays\Sculptor\Contracts\WithoutCacheInTransactions;
use Illuminate\Support\Facades\DB;

class TransactionalPostList extends PaginatedPostsForUser implements WithoutCacheInTransactions {}

// The instance may be created before the transaction starts.
$query = TransactionalPostList::make(userId: 42, status: 'published');

$posts = DB::transaction(
    fn () => $query->perPage(20)->effect(),
);

// Outside the transaction, ordinary caching is restored.
$posts = $query->effect();
```

The decision uses the **actual builder connection's** `transactionLevel()`, after applying global scopes and `beforeQuery` callbacks. It does not check only Laravel's default connection. A transaction on an unrelated connection does not disable this query's cache; nested transactions keep bypass active until the outer transaction ends.

During bypass, Sculptor neither reads nor writes the result cache or the underlying Query Cache entries, including models with their own cache defaults. This covers all 13 result traits, pagination totals, and eager-loaded relations, including nested and polymorphic relations. Relations inherit the root query's bypass even when they use a different connection. Existing relation constraints and callbacks still run. The root connection determines the transaction policy; this is not a distributed transaction across those connections.

The policy is evaluated for each execution. It does not change the query instance's TTL or explicit `withoutCache()` setting, flush entries, or cache the transactional result after commit. Existing cached entries remain available outside the transaction. After writes, invalidate affected scopes or tags **after successful commit** as usual; the marker does not perform automatic invalidation. Independently enabled model caches retain their own tags and invalidation policy outside bypass.

A database read still follows the transaction's isolation level. This policy does not change isolation or replace database locks. It covers relations eagerly loaded as part of this query execution; subsequent lazy loads on returned models are separate reads.

Built-in result traits compose `PreparesQueryForExecution` and integrate the guard automatically. Custom result-cache execution should use `rememberResult()`. Custom builder-cache effects can compose `PreparesQueryForExecution`, finish building their query, and call `prepareQueryForExecution($query)` before executing it. Returning a raw builder through `query()` and executing it yourself bypasses the result traits' execution policy.

### Invalidate by Constructor Arguments

`#[CacheScope]` lets you invalidate the same domain inputs you pass to a query, without managing tag strings. For the two marked parameters in `PaginatedPostsForUser`:

```php
// Only this user's published results, across all pages and page sizes.
PaginatedPostsForUser::flushScope(userId: 42, status: 'published');

// Every status and every page for user 42. Other users stay cached.
PaginatedPostsForUser::flushScope(userId: 42);

// Published results for all users of this query class.
PaginatedPostsForUser::flushScope(status: 'published');

// Only entries whose status scope is null; omission means any status.
PaginatedPostsForUser::flushScope(userId: 42, status: null);

// All entries belonging to this query class.
PaginatedPostsForUser::flushBaseCache();
```

Supplied arguments match **together**; omitted marked arguments are wildcards, even if their constructor parameters are required or have defaults. Explicit `null` matches only `null`. Mark only the parameters you want to use for invalidation: an unmarked status can still filter SQL, while a marked `userId` groups all that user's statuses.

Scopes cover matching pages, page sizes, projections, and filters, including pagination totals and cacheable relations in builder mode. They are local to the concrete query class and selected store. Invalidating a scope does not construct the query or execute query SQL. Call invalidation explicitly after application writes; Sculptor does not infer domain invalidation from model changes.

At least one marked argument is required. Prefer named arguments; positional arguments follow the order of marked parameters, skipping unmarked dependencies. Unknown or unmarked arguments, duplicate assignments, and invalid supplied types raise `InvalidArgumentException` before store access. No scope definition raises `LogicException`. Store failures propagate and boolean flush results are returned.

<details>
<summary>Scope identities, tag count, and upgrade behavior</summary>

Each entry receives a full-scope tag plus a tag for every other non-empty subset. Two marked fields produce three scope tags: `userId`, `status`, and `(userId, status)`. Their values and parameter names are normalized and hashed with the concrete query class; these are internal tags, not strings applications should construct. The class's base tag and any manual tags are also retained.

For `n` marked parameters, there are `2^n - 1` scope tags per entry. Keep marked parameters focused. Constructor arguments that only filter the query can remain unmarked.

Supported values are strings, integers, booleans, finite floats, `null`, unit/backed enums, Eloquent models, and `CacheScopeKey` objects. Types are preserved: `42` and `'42'` differ when the parameter permits both. Pass the enum instance rather than its backing string. Invalidation validates declared types without weak scalar coercion; integer-to-float widening follows PHP. Arrays, arbitrary objects (including plain `Stringable`), resources, `NAN`, and infinite floats are rejected.

Only promoted constructor parameters can carry `#[CacheScope]`. Inherited constructors retain their scopes, including private promoted properties. A replacement constructor must declare its own scopes. Invalid declarations or uninitialized scope properties raise `LogicException`.

**Upgrading from exact-only scopes:** old composite entries lack subset tags. Run `YourQuery::flushBaseCache()` once for affected classes before relying on partial invalidation. Full tuple invalidation still works on old entries when all marked values are supplied explicitly. Single-parameter scopes keep their original tag set.

</details>

### Models and Custom Scope Values

A model parameter can identify a scope directly. In your query constructor:

```php
public function __construct(
    #[CacheScope] private readonly User $user,
) {}
```

Use `$this->user->getKey()` when building the query, then pass a model instance to both creation and invalidation:

```php
$query = UserPosts::make(user: $user);
UserPosts::flushScope(user: $user);
```

The identity is the **concrete model class, connection name, and primary key**, including integer/string key type. Two instances of the same record share a scope; attributes, dirty state, loaded relations, and the `exists` flag do not affect it. Models are not serialized into tags. Normalization does not open a database connection or load the record.

Keys must be non-null integers or strings; UUIDs and zero are supported. Keyed references and deleted model instances remain valid. An implicit connection uses the current default connection name. When switching tenants behind one connection name, add a tenant scope or a custom identity. Pass the constructor's model type, not an interchangeable scalar ID, and invalidate old identities if keys or connections change.

For data objects, implement `Atldays\Sculptor\Contracts\CacheScopeKey`:

```php
use Atldays\Sculptor\Contracts\CacheScopeKey;

final readonly class PostScopeData implements CacheScopeKey
{
    public function __construct(public int $tenantId, public int $userId) {}

    public function cacheScopeKey(): string
    {
        return json_encode(['posts', $this->tenantId, $this->userId], JSON_THROW_ON_ERROR);
    }
}
```

Mark the promoted data-object parameter with `#[CacheScope]`, then pass equivalent objects to `make()` and `flushScope()`. Its string is a component of the hashed identity, not a literal tag for `flushCacheTags()`.

Custom keys control their own namespace: the DTO class is deliberately excluded, while the query class, parameter names, and other marked values still participate. Use domain prefixes or structured encoding to distinguish unrelated custom identities. `CacheScopeKey` takes priority over built-in model/enum normalization. Its method must be deterministic and free of SQL or other I/O; it may run more than once. Exceptions propagate, and an empty component is allowed and distinct from `null` or an ordinary string scope.

### Share Explicit Domain Tags

Use manual tags when several query classes should be invalidated together:

```php
$query = PaginatedPostsForUser::make(userId: 42)
    ->mergeCacheTags('posts:user:42');

$posts = $query->effect();

// Any query in this store carrying either tag is invalidated.
PaginatedPostsForUser::flushCacheTags('posts:user:42', 'posts:user:57');
```

`setCacheTags(...)` replaces manual tags; `mergeCacheTags(...)` appends them. Automatic scope and base tags remain. Manual tags are application-defined; place shared defaults in your query constructor when every instance should use them.

`flushCacheTags()` invalidates the **union** of entries carrying any supplied tag. It does not implicitly include the calling class's base tag. A shared tag can invalidate other query classes, either cache strategy, and tagged Laravel entries in the same backend namespace. Distinct configured store names only isolate data when their backend namespaces are separate.

At least one `string|Stringable` tag is required. Values are converted without trimming, case changes, or prefixes; empty strings are rejected before any store access. All tags are validated before invalidation. The method returns the store's boolean result and propagates errors, with no query construction, query SQL, or global-flush fallback.

`flushBaseCache()` invalidates every entry carrying the calling class's fully qualified base tag. You can also run:

```bash
php artisan sculptor:flush-cache 'App\Queries\Post\PaginatedPostsForUser'
```

The command exits with `0` on success and `1` for an invalid query class or failed flush.

## Extend and Observe

The presets compose public traits. For most applications, start with a preset and choose a result trait; use these lower-level pieces for custom query bases:

| Concern (`Atldays\Sculptor\Concerns`) | Responsibility |
| --- | --- |
| `HasModel` | Model binding and model creation |
| `HasQuery` | Selects, relations, limits, and builder creation |
| `HasQueryWithFilters` | Query construction with composable filters |
| `HasResult` | Container-backed `make()`, `result()`, and execution events |
| `PreparesQueryForExecution` | SQL-builder preparation and execution-time cache policy |
| `HasPagination` | Numbered pagination settings and `PaginationData` |
| `HasCursorPagination` | Cursor settings and `CursorPaginationData` |
| `HasResultColumn` | Projection-column defaults and overrides |
| `HasPluckKey` | Pluck-key defaults and overrides |
| `HasAggregateFunction` | Aggregate-operation defaults and overrides |
| `HasCache` | TTL, tags, stores, scopes, and invalidation helpers |
| `InteractsWithResultCache` | Result-cache execution |
| `InteractsWithBuilderCache` | Builder-cache configuration |
| `PreparesCacheableRelations` | Cache settings for eager-loaded relations |

The 13 `QueryResult*` traits are listed in [Choose Your Result](#choose-your-result). When composing custom bases, implement the matching `Atldays\Sculptor\Contracts` interfaces as the presets do.

Both cached presets implement the optional `WithCacheTagInvalidation` and `WithCacheScopeInvalidation` contracts. These extend the existing cache contracts without adding requirements to custom implementations of `WithCache`, `WithResultCache`, or `WithBuilderCache`.

### Execution Events

`::result()` dispatches `Atldays\Sculptor\Events\ResultExecuted` for successful reads, cache hits, and exceptions. The event exposes `name`, `startedAt`, `finishedAt`, and `duration()` in milliseconds. Direct `->effect()` calls do not dispatch this event.

<details>
<summary>Attribute and cache utilities</summary>

`Atldays\Sculptor\Support\AttributeReader` reads the nearest matching class attribute from an object or class name:

```php
use App\Queries\Post\PaginatedPostsForUser;
use Atldays\Sculptor\Attributes\CacheFor;
use Atldays\Sculptor\Support\AttributeReader;

$attribute = AttributeReader::make(PaginatedPostsForUser::class)->get(CacheFor::class);
$seconds = $attribute?->seconds();
```

Both cache strategies use the object-based `Atldays\Sculptor\Cache\CacheScopeResolver`. Its internal API follows the same factory pattern:

```php
use Atldays\Sculptor\Cache\CacheScopeResolver;

$query = PaginatedPostsForUser::make(userId: 42, status: 'published');
$resolver = CacheScopeResolver::make($query);

$fullTag = $resolver->tag();
$allScopeTags = $resolver->tags();

$partialTag = CacheScopeResolver::make(PaginatedPostsForUser::class)
    ->tagForInvalidation(['userId' => 42]);
```

`tag()` / `tags()` require an object and read its current values; no-scope queries return `null` / `[]`. Each resolution reads current model/custom identities without memoizing their values. On a class-only context these methods throw `LogicException`. `tagForArguments()` retains full tuple resolution with constructor defaults; `tagForInvalidation()` uses only supplied arguments. Neither constructs the query. Application code should normally use `flushScope()` and `cacheTags()`.

The internal classes are grouped by responsibility:

```text
src/
├── Cache/
│   ├── Enums/
│   │   └── CachedNull.php
│   ├── CacheScopeResolver.php
│   ├── MorphToQueryAdapter.php
│   └── TransactionCacheGuard.php
├── Enums/
│   └── AggregateFunction.php
└── Support/
    └── AttributeReader.php
```

`HasResult` owns result construction and execution events. `PreparesQueryForExecution` owns SQL preparation for query result traits. `TransactionCacheGuard` retains a builder context, following the constructor / `make()` API used by the other support objects:

```php
use Atldays\Sculptor\Cache\TransactionCacheGuard;

$guard = TransactionCacheGuard::make($builder);
$prepared = $guard->prepare();
```

It prepares an independent builder for execution; reusing the guard observes the current transaction state without retaining a bypass flag. `bypass()` acts on the builder supplied to the guard. These are internal helpers; application code should normally use the query interfaces and result traits.

`MorphToQueryAdapter` applies the guard after each concrete polymorphic type's existing constraints, including scopes and callbacks that enable caching. Laravel has no public hook for this step, so access to its internal replay buffer and constraint map is isolated in the adapter. Integration tests cover native and Query Cache relations, multiple types, morph maps, buffered calls, soft deletes, and nested eager loads on the supported Laravel versions.

</details>

## Testing and Contributing

Query classes give substantial reads a stable home and isolate their rules so they can be exercised against your application's database and fixtures. Sculptor's test suite covers plain queries, filters, result types, pagination, both cache strategies, and invalidation across supported PHP and Laravel versions, with PostgreSQL checks for precise aggregate types.

See [CONTRIBUTING.md](CONTRIBUTING.md) for development requirements and verification commands, including formatting, PHPStan, PHPUnit, and Composer audit.

## License

[MIT](LICENSE.md)
