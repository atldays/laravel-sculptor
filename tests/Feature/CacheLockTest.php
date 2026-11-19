<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Attributes\{CacheLock, CacheStore};
use Atldays\Sculptor\BuilderCachedQuery;
use Atldays\Sculptor\Concerns\{QueryResultFirst, QueryResultPaginated};
use Atldays\Sculptor\Contracts\{WithPaginatedResult, WithoutCacheInTransactions};
use Atldays\Sculptor\Tests\Fixtures\Models\{LockDefaultAuthor, LockDefaultNativeMorphPost, LockDefaultPost, PlainMorphAuthor, Post};
use Atldays\Sculptor\Tests\Fixtures\Queries;
use Atldays\Sculptor\Tests\Support\RecordingLockStore;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\{Builder, ModelNotFoundException};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Cache, DB, Schema};
use PHPUnit\Framework\Attributes\DataProvider;

class CacheLockTest extends TestCase
{
    private RecordingLockStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new RecordingLockStore(serializesValues: true);
        $store = $this->store;
        Cache::extend('recording-lock', fn () => new Repository($store));
        config()->set('cache.stores.array', ['driver' => 'recording-lock']);
        Cache::forgetDriver('array');
        DB::table('authors')->insert(['id' => 42, 'name' => 'Owner']);
        DB::table('posts')->insert(['id' => 1, 'author_id' => 42, 'title' => 'Original', 'published' => true]);
    }

    public static function strategies(): iterable
    {
        yield 'result' => [Queries\LockedCachedPostsQuery::class];
        yield 'builder' => [Queries\LockedBuilderCachedPostsQuery::class];
    }

    #[DataProvider('strategies')]
    public function test_attribute_guards_cold_results_and_runtime_changes_reuse_the_same_warm_entry(string $class): void
    {
        $query = $class::make();
        $this->assertSame('Original', $query->effect()->sole()->title);
        $this->assertSame([23], array_column($this->store->locksRequested, 1));
        DB::table('posts')->update(['title' => 'Updated']);
        DB::enableQueryLog();
        DB::flushQueryLog();

        foreach ([$query->withoutCacheLock(), $class::make()->setCacheLock(41, 0), $class::make()] as $warm) {
            $this->assertSame('Original', $warm->effect()->sole()->title);
        }

        $this->assertSame([], DB::getQueryLog());
        $this->assertCount(1, $this->store->locksRequested);
        $this->store->forgetResults();
        $this->assertSame('Updated', $query->effect()->sole()->title);
        $this->assertCount(1, $this->store->locksRequested);
        $this->store->forgetResults();
        $this->assertSame('Updated', $query->setCacheLock(11, 0)->effect()->sole()->title);
        $this->assertSame([23, 11], array_column($this->store->locksRequested, 1));
    }

    #[DataProvider('strategies')]
    public function test_timeout_executes_no_sql_and_does_not_change_the_cache_policy(string $class): void
    {
        $query = $class::make();
        $query->effect();
        $name = $this->store->locksRequested[0][0];
        $this->store->forgetResults();
        $held = $this->store->lock($name, 60);
        $this->assertTrue($held->get());
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $query->effect();
            $this->fail('The producer must not run without the lock.');
        } catch (LockTimeoutException) {
            $this->assertSame([], DB::getQueryLog());
            $this->assertSame(23, $query->cacheLockOptions()->seconds);
        } finally {
            $this->assertTrue($held->release());
        }

        $this->assertSame('Original', $query->effect()->sole()->title);
        $this->assertCount(1, DB::getQueryLog());
    }

    public static function bypassModes(): iterable
    {
        foreach (self::strategies() as $name => [$class]) {
            foreach (['manual', 'zero', 'negative', 'transaction'] as $mode) {
                yield "$name / $mode" => [$class, $mode];
            }
        }
    }

    #[DataProvider('bypassModes')]
    public function test_bypass_does_not_request_locks_or_publish_results(string $class, string $mode): void
    {
        $query = $class === Queries\LockedCachedPostsQuery::class
            ? new #[CacheLock(23, 0)] class extends Queries\LockedCachedPostsQuery implements WithoutCacheInTransactions {}
        : new #[CacheLock(23, 0)] class extends Queries\LockedBuilderCachedPostsQuery implements WithoutCacheInTransactions {};

        match ($mode) {
            'manual' => $query->withoutCache(),
            'zero' => $query->setCacheFor(0),
            'negative' => $query->setCacheFor(-1),
            default => DB::beginTransaction(),
        };
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $this->assertSame('Original', $query->effect()->sole()->title);
            $this->assertSame('Original', $query->effect()->sole()->title);
            $this->assertSame([], $this->store->locksRequested);
            $this->assertCount(2, DB::getQueryLog());
        } finally {
            if ($mode === 'transaction') {
                DB::rollBack();
            }
        }

        $query->withoutCache(false)->setCacheFor(600)->effect();
        $this->assertCount(1, $this->store->locksRequested);
    }

    public function test_null_results_are_shared_and_invalidated_without_recomputing(): void
    {
        DB::table('posts')->delete();
        $query = Queries\GenericCachedFirstPostQuery::make()->setCacheLock(13, 0);
        DB::enableQueryLog();
        $this->assertNull($query->effect());
        $this->assertNull($query->effect());
        $this->assertCount(1, DB::getQueryLog());
        $this->assertCount(1, $this->store->locksRequested);
        DB::table('posts')->insert(['title' => 'Created']);
        $query::flushCache();
        $this->assertSame('Created', $query->effect()->title);
        $this->assertCount(2, $this->store->locksRequested);
    }

    public function test_result_exceptions_release_the_lock_and_remain_uncached(): void
    {
        DB::table('posts')->delete();
        $query = Queries\CachedFirstOrFailPostsQuery::make()->setCacheLock(17, 0);

        try {
            $query->effect();
            $this->fail('Missing models must still throw.');
        } catch (ModelNotFoundException) {
            $name = $this->store->locksRequested[0][0];
            $lock = $this->store->lock($name, 60);
            $this->assertTrue($lock->get());
            $this->assertTrue($lock->release());
        }

        DB::table('posts')->insert(['title' => 'Created']);
        $this->assertSame('Created', $query->effect()->title);
    }

    #[DataProvider('strategies')]
    public function test_the_configured_store_is_used_for_the_result_and_its_lock(string $class): void
    {
        $secondary = new RecordingLockStore;
        Cache::extend('secondary-lock', fn () => new Repository($secondary));
        config()->set('cache.stores.secondary', ['driver' => 'secondary-lock']);
        $query = $class === Queries\LockedCachedPostsQuery::class
            ? new #[CacheStore('secondary')] class extends Queries\LockedCachedPostsQuery {}
        : new #[CacheStore('secondary')] class extends Queries\LockedBuilderCachedPostsQuery {};
        $this->assertSame('Original', $query->effect()->sole()->title);
        $this->assertSame([23], array_column($secondary->locksRequested, 1));
        $this->assertSame([], $this->store->locksRequested);
    }

    public function test_builder_relations_inherit_settings_and_can_override_or_disable_them(): void
    {
        $query = Queries\LockedBuilderCachedPostsQuery::make()->withRelations([
            'author' => fn ($author) => $author->cacheLock(31, 0),
            'author.posts' => fn ($posts) => $posts->withoutCacheLock(),
        ]);
        $post = $query->effect()->sole();
        $this->assertSame('Owner', $post->author->name);
        $this->assertSame('Original', $post->author->posts->sole()->title);
        $this->assertSame([23, 31], array_column($this->store->locksRequested, 1));
        $this->assertSame('Original', $query->effect()->sole()->author->posts->sole()->title);
        $this->assertCount(2, $this->store->locksRequested);
    }

    public function test_relation_override_is_inherited_by_nested_relations(): void
    {
        $query = Queries\LockedBuilderCachedPostsQuery::make()->withRelations([
            'author' => fn ($author) => $author->cacheLock(31, 0),
            'author.posts',
        ]);
        $this->assertSame('Original', $query->effect()->sole()->author->posts->sole()->title);
        $this->assertSame([23, 31, 31], array_column($this->store->locksRequested, 1));
    }

    private function modelDefaultQuery(): Queries\BuilderCachedAllPostsQuery
    {
        return new class() extends Queries\BuilderCachedAllPostsQuery
        {
            protected string $model = LockDefaultPost::class;
        };
    }

    public function test_model_lock_defaults_are_kept_without_a_sculptor_override(): void
    {
        $query = $this->modelDefaultQuery();
        $this->assertNull($query->cacheLockOptions());
        $this->assertSame('Owner', $query->effect()->sole()->author->name);
        $this->assertSame([37, 43], array_column($this->store->locksRequested, 1));
    }

    public function test_explicit_opt_out_disables_model_default_and_implicit_relation_locks(): void
    {
        $query = $this->modelDefaultQuery()->withoutCacheLock();
        $this->assertSame('Owner', $query->effect()->sole()->author->name);
        $this->assertSame([], $this->store->locksRequested);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame('Owner', $query->effect()->sole()->author->name);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_query_options_reach_model_default_and_nested_eager_loads(): void
    {
        $query = $this->modelDefaultQuery()->setCacheLock(23, 0)->withRelations('author.posts');
        $this->assertSame('Original', $query->effect()->sole()->author->posts->sole()->title);
        $this->assertGreaterThanOrEqual(3, count($this->store->locksRequested));
        $this->assertSame([23], array_values(array_unique(array_column($this->store->locksRequested, 1))));
    }

    public function test_implicit_plain_relations_are_not_silently_made_cacheable(): void
    {
        $model = new class() extends Post
        {
            protected $table = 'posts';

            protected $with = ['plainAuthor'];
        };
        $query = new class($model::class) extends Queries\BuilderCachedAllPostsQuery
        {
            public function __construct(string $model)
            {
                $this->model = $model;
            }
        };
        $query->setCacheLock(23, 0);
        $this->assertSame('Owner', $query->effect()->sole()->plainAuthor->name);
        $this->assertSame([23], array_column($this->store->locksRequested, 1));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame('Owner', $query->effect()->sole()->plainAuthor->name);
        $this->assertCount(1, DB::getQueryLog());
        $this->assertCount(1, $this->store->locksRequested);
    }

    public static function freshReads(): iterable
    {
        foreach (self::strategies() as $name => [$class]) {
            foreach (['SQL lock callback', 'write PDO'] as $mode) {
                yield "$name / $mode" => [$class, $mode];
            }
        }
    }

    public static function plainMorphPolicies(): iterable
    {
        yield 'inherited lock' => [true];
        yield 'explicit opt-out' => [false];
    }

    #[DataProvider('plainMorphPolicies')]
    public function test_lock_policy_reaches_cacheable_types_through_a_plain_morph_relation(bool $enabled): void
    {
        Schema::table('authors', fn (Blueprint $table) => $table->nullableMorphs('subject'));
        DB::table('authors')->update(['subject_type' => LockDefaultPost::class, 'subject_id' => 1]);
        $model = new class() extends Post
        {
            protected $table = 'posts';

            protected $with = ['plainAuthor'];

            /** @return BelongsTo<PlainMorphAuthor, $this> */
            public function plainAuthor(): BelongsTo
            {
                return $this->belongsTo(PlainMorphAuthor::class, 'author_id');
            }
        };
        $query = new class($model::class) extends Queries\BuilderCachedAllPostsQuery
        {
            public function __construct(string $model)
            {
                $this->model = $model;
            }
        };
        $enabled ? $query->setCacheLock(23, 0) : $query->withoutCacheLock();
        $post = $query->effect()->sole();
        $this->assertSame('Original', $post->plainAuthor->subject->title);
        $this->assertSame('Owner', $post->plainAuthor->subject->author->name);
        $this->assertSame($enabled ? [23, 23, 23] : [], array_column($this->store->locksRequested, 1));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame('Owner', $query->effect()->sole()->plainAuthor->subject->author->name);
        $this->assertCount(1, DB::getQueryLog());
    }

    #[DataProvider('freshReads')]
    public function test_sql_lock_callbacks_and_write_pdo_bypass_protection(string $class, string $mode): void
    {
        $prepared = $class::make()->query();

        if ($mode === 'SQL lock callback') {
            $prepared->getQuery()->beforeQuery(fn ($base) => $base->lockForUpdate());
        } else {
            $prepared->useWritePdo();
        }

        $query = $class === Queries\LockedCachedPostsQuery::class
            ? new class($prepared) extends Queries\LockedCachedPostsQuery
            {
                public function __construct(private readonly BuilderContract $prepared) {}

                public function query(): BuilderContract
                {
                    return $this->prepared;
                }
            }
        : new class($prepared) extends Queries\LockedBuilderCachedPostsQuery
        {
            public function __construct(private readonly BuilderContract $prepared) {}

            public function query(): BuilderContract
            {
                return $this->prepared;
            }
        };
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame('Original', $query->effect()->sole()->title);
        $this->assertSame('Original', $query->effect()->sole()->title);
        $this->assertCount(2, DB::getQueryLog());
        $this->assertSame([], $this->store->locksRequested);
    }

    public static function morphModels(): iterable
    {
        yield 'Query Cache MorphTo' => [LockDefaultPost::class];
        yield 'Laravel MorphTo' => [LockDefaultNativeMorphPost::class];
    }

    #[DataProvider('morphModels')]
    public function test_morph_type_constraints_can_override_inherited_lock_options(string $model): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->nullableMorphs('subject'));
        DB::table('posts')->update(['subject_type' => LockDefaultAuthor::class, 'subject_id' => 42]);
        $query = new class($model) extends Queries\BuilderCachedAllPostsQuery
        {
            public function __construct(string $model)
            {
                $this->model = $model;
            }
        };
        $seen = [];
        $query->setCacheLock(23, 0)->withRelations([
            'subject' => function (MorphTo $relation) use (&$seen): void {
                $relation->morphWith([LockDefaultAuthor::class => ['posts']])->constrain([
                    LockDefaultAuthor::class => function (Builder $author) use (&$seen): void {
                        $seen[] = $author->getQuery()->getCacheLockOptions()?->seconds;
                        $author->where('name', 'Owner')->cacheLock(19, 0);
                    },
                ]);
            },
        ]);
        $post = $query->effect()->sole();
        $this->assertSame('Owner', $post->subject->name);
        $this->assertSame('Original', $post->subject->posts->sole()->title);
        $this->assertSame([23], $seen);
        $leases = array_values(array_unique(array_column($this->store->locksRequested, 1)));
        sort($leases);
        $this->assertSame([19, 23], $leases);
    }

    #[DataProvider('morphModels')]
    public function test_morph_type_model_defaults_can_be_disabled_without_disabling_result_caching(string $model): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->nullableMorphs('subject'));
        DB::table('posts')->update(['subject_type' => LockDefaultAuthor::class, 'subject_id' => 42]);
        $query = new class($model) extends Queries\BuilderCachedAllPostsQuery
        {
            public function __construct(string $model)
            {
                $this->model = $model;
            }
        };
        $seen = [];
        $query->withoutCacheLock()->withRelations([
            'subject' => function (MorphTo $relation) use (&$seen): void {
                $relation->constrain([
                    LockDefaultAuthor::class => function (Builder $author) use (&$seen): void {
                        $seen[] = $author->getQuery()->getCacheLockOptions();
                        $author->where('name', 'Owner');
                    },
                ]);
            },
        ]);
        $this->assertSame('Owner', $query->effect()->sole()->subject->name);
        $this->assertSame([null], $seen);
        $this->assertSame([], $this->store->locksRequested);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame('Owner', $query->effect()->sole()->subject->name);
        $this->assertSame([], DB::getQueryLog());
    }

    public static function pages(): iterable
    {
        foreach (self::operations() as $name => [$class, $resultCache]) {
            if (preg_match('/^(paginate|simple|cursor) /', $name, $matches)) {
                yield $name => [$class, $resultCache, $matches[1]];
            }
        }
    }

    #[DataProvider('pages')]
    public function test_pages_and_count_queries_use_distinct_locks_and_share_cached_totals(string $class, bool $resultCache, string $kind): void
    {
        DB::table('posts')->insert(['id' => 2, 'title' => 'Second']);
        $first = $class::make()->setCacheLock(17, 0)->perPage(1);
        $page = $first->effect();
        $second = $class::make()->setCacheLock(17, 0)->perPage(1);

        if ($kind === 'cursor') {
            $second->cursor($page->nextCursor());
        } else {
            $second->page(2);
        }

        $this->assertSame('Second', $second->effect()->items()[0]->title);
        $count = $kind === 'paginate' && !$resultCache ? 3 : 2;
        $this->assertCount($count, $this->store->locksRequested);
        $this->assertCount($count, array_unique(array_column($this->store->locksRequested, 0)));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame('Original', $first->effect()->items()[0]->title);
        $this->assertSame('Second', $second->effect()->items()[0]->title);
        $this->assertSame([], DB::getQueryLog());
        $this->assertCount($count, $this->store->locksRequested);
    }

    public static function operations(): iterable
    {
        $first = new class() extends BuilderCachedQuery
        {
            use QueryResultFirst;

            protected string $model = Post::class;
        };
        $paginated = new class() extends BuilderCachedQuery implements WithPaginatedResult
        {
            use QueryResultPaginated;

            protected string $model = Post::class;
        };
        $pairs = [
            'all' => [Queries\CachedAllPostsQuery::class, Queries\BuilderCachedAllPostsQuery::class],
            'collection' => [Queries\GenericCachedPostsQuery::class, Queries\CachedPostsQuery::class],
            'first' => [Queries\GenericCachedFirstPostQuery::class, $first::class],
            'firstOrFail' => [Queries\CachedFirstOrFailPostsQuery::class, Queries\BuilderCachedFirstOrFailPostsQuery::class],
            'exists' => [Queries\CachedExistsPostsQuery::class, Queries\BuilderCachedExistsPostsQuery::class],
            'count' => [Queries\CachedCountPostsQuery::class, Queries\BuilderCachedCountPostsQuery::class],
            'value' => [Queries\CachedValueProjectionQuery::class, Queries\BuilderCachedValueProjectionQuery::class],
            'pluck' => [Queries\CachedPluckProjectionQuery::class, Queries\BuilderCachedPluckProjectionQuery::class],
            'aggregate' => [Queries\CachedAggregateProjectionQuery::class, Queries\BuilderCachedAggregateProjectionQuery::class],
            'sole' => [Queries\CachedSolePostsQuery::class, Queries\BuilderCachedSolePostsQuery::class],
            'paginate' => [Queries\CachedPaginatedPostsQuery::class, $paginated::class],
            'simple' => [Queries\CachedSimplePaginatedPostsQuery::class, Queries\BuilderCachedSimplePaginatedPostsQuery::class],
            'cursor' => [Queries\CachedCursorPaginatedPostsQuery::class, Queries\BuilderCachedCursorPaginatedPostsQuery::class],
        ];

        foreach ($pairs as $kind => $classes) {
            foreach ($classes as $index => $class) {
                yield "$kind / " . ($index === 0 ? 'result' : 'builder') => [$class, $index === 0];
            }
        }
    }

    #[DataProvider('operations')]
    public function test_result_traits_cache_under_the_lock_and_hits_execute_no_sql_or_lock(string $class, bool $resultCache): void
    {
        $query = $class::make()->setCacheLock(17, 0);
        DB::enableQueryLog();
        $first = $query->effect();
        $locks = $this->store->locksRequested;
        $this->assertNotEmpty($locks);
        $this->assertSame([17], array_values(array_unique(array_column($locks, 1))));
        $this->assertCount($resultCache ? 1 : count(DB::getQueryLog()), $locks);
        DB::flushQueryLog();
        $this->assertEquals($first, $query->effect());
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame($locks, $this->store->locksRequested);
    }
}
