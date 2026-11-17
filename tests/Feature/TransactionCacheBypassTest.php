<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\{BuilderCachedQuery, CachedQuery};
use Atldays\Sculptor\Concerns as Results;
use Atldays\Sculptor\Contracts\{WithCursorPaginatedResult, WithPaginatedResult};
use Atldays\Sculptor\Tests\Fixtures\Models\{AutomaticallyCachedAuthor, AutomaticallyCachedPost, PlainPost};
use Atldays\Sculptor\Tests\Fixtures\Queries;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{MorphTo, Relation};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Cache, DB, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class TransactionCacheBypassTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('posts', fn (Blueprint $table) => $table->nullableMorphs('subject'));
        DB::table('authors')->insert(['id' => 42, 'name' => 'Original author']);
        DB::table('posts')->insert(['id' => 1, 'author_id' => 42, 'title' => 'Original', 'published' => true]);
    }

    public static function strategies(): iterable
    {
        yield 'result cache' => ['result'];
        yield 'builder cache' => ['builder'];
    }

    public static function morphStrategies(): iterable
    {
        foreach (self::strategies() as $name => [$strategy]) {
            yield "$name / Laravel MorphTo" => [$strategy, true];
            yield "$name / Query Cache MorphTo" => [$strategy, false];
        }
    }

    public static function resultStrategies(): iterable
    {
        foreach (['result', 'builder'] as $strategy) {
            foreach (['all', 'collection', 'first', 'firstOrFail', 'sole', 'exists', 'count', 'value', 'pluck', 'aggregate', 'paginate', 'simple', 'cursor'] as $kind) {
                yield "$strategy / $kind" => [$strategy, $kind];
            }
        }
    }

    #[DataProvider('resultStrategies')]
    public function test_every_result_bypasses_existing_entries_and_preserves_them_after_rollback(string $strategy, string $kind): void
    {
        $query = $this->createQuery($strategy, $kind);
        $original = $this->normalize($kind, $query->effect());
        DB::beginTransaction();

        try {
            DB::table('posts')->update(['title' => 'Changed']);

            if (in_array($kind, ['count', 'aggregate'], true)) {
                DB::table('posts')->insert(['id' => 2, 'title' => 'Second', 'published' => true]);
            } elseif ($kind === 'exists') {
                DB::table('posts')->delete();
            }

            DB::enableQueryLog();
            DB::flushQueryLog();
            $fresh = $this->normalize($kind, $query->effect());
            $this->assertNotSame($original, $fresh);
            $this->assertNotEmpty(DB::getQueryLog());
        } finally {
            DB::rollBack();
        }

        DB::flushQueryLog();
        $this->assertSame($original, $this->normalize($kind, $query->effect()));
        $this->assertCount(0, DB::getQueryLog());
    }

    #[DataProvider('resultStrategies')]
    public function test_cold_transaction_reads_do_not_touch_either_cache_layer_or_publish_rolled_back_data(string $strategy, string $kind): void
    {
        $query = $this->createQuery($strategy, $kind)->withRelations('author.posts');
        $cache = Cache::getFacadeRoot();
        DB::beginTransaction();

        try {
            DB::table('posts')->update(['title' => 'Uncommitted']);
            DB::table('authors')->update(['name' => 'Uncommitted author']);
            Cache::shouldReceive('store')->never();
            Cache::shouldReceive('driver')->never();
            $result = $query->effect();
            $this->assertSame($this->expectedResult($kind, 'Uncommitted'), $this->normalize($kind, $result));
            $post = match ($kind) {
                'all', 'collection' => $result->sole(),
                'first', 'firstOrFail', 'sole' => $result,
                'paginate', 'simple', 'cursor' => $result->items()[0],
                default => null,
            };

            if ($post !== null) {
                $this->assertSame('Uncommitted author', $post->author->name);
                $this->assertSame('Uncommitted', $post->author->posts->sole()->title);
            }
        } finally {
            DB::rollBack();
            Cache::swap($cache);
        }

        DB::table('posts')->update(['title' => 'Committed']);
        $this->assertSame($this->expectedResult($kind, 'Committed'), $this->normalize($kind, $query->effect()));
    }

    #[DataProvider('strategies')]
    public function test_commit_restores_caching_without_flushing_existing_entries(string $strategy): void
    {
        $query = $this->createQuery($strategy);
        $this->assertSame('Original', $query->effect()->sole()->title);
        DB::transaction(function () use ($query): void {
            DB::table('posts')->update(['title' => 'Committed']);
            $this->assertSame('Committed', $query->effect()->sole()->title);
        });

        $this->assertSame('Original', $query->effect()->sole()->title);
        $query::flushBaseCache();
        // A native model cache can exist independently of Sculptor's result cache.
        Cache::store('array')->flush();
        $this->assertSame('Committed', $query->effect()->sole()->title);
    }

    #[DataProvider('strategies')]
    public function test_nested_transactions_keep_bypassing_until_the_outer_transaction_finishes(string $strategy): void
    {
        $query = $this->createQuery($strategy);
        $query->effect();
        DB::beginTransaction();

        try {
            DB::table('posts')->update(['title' => 'Outer']);
            DB::beginTransaction();
            DB::table('posts')->update(['title' => 'Inner']);
            $this->assertSame('Inner', $query->effect()->sole()->title);
            DB::rollBack();
            $this->assertSame('Outer', $query->effect()->sole()->title);
        } finally {
            DB::rollBack();
        }

        $this->assertSame('Original', $query->effect()->sole()->title);
    }

    #[DataProvider('strategies')]
    public function test_cached_and_nested_relations_are_bypassed_even_when_constraints_reenable_native_cache(string $strategy): void
    {
        $query = $this->createQuery($strategy)->withRelations([
            'author' => fn (BuilderContract $author) => $author
                ->cacheFor(600)
                ->withGlobalScope('cache-enabled', fn (BuilderContract $query) => $query->cacheFor(600))
                ->beforeQuery(fn ($base) => $base->cacheFor(600)),
            'author.posts',
        ]);
        $query->effect();
        DB::beginTransaction();

        try {
            DB::table('authors')->update(['name' => 'Fresh author']);
            DB::table('posts')->update(['title' => 'Fresh post']);
            $post = $query->effect()->sole();
            $this->assertSame('Fresh post', $post->title);
            $this->assertSame('Fresh author', $post->author->name);
            $this->assertSame('Fresh post', $post->author->posts->sole()->title);
        } finally {
            DB::rollBack();
        }

        $this->assertSame('Original author', $query->effect()->sole()->author->name);
        $this->assertSame('Original', $query->effect()->sole()->author->posts->sole()->title);
    }

    #[DataProvider('strategies')]
    public function test_morph_types_and_their_nested_relations_bypass_model_default_caches(string $strategy): void
    {
        DB::table('posts')->update(['subject_type' => AutomaticallyCachedAuthor::class, 'subject_id' => 42]);
        $query = $this->createQuery($strategy)->withRelations([
            'subject' => fn (MorphTo $relation) => $relation->morphWith([AutomaticallyCachedAuthor::class => ['posts']]),
        ]);
        $this->assertSame('Original author', $query->effect()->sole()->subject->name);
        DB::beginTransaction();

        try {
            DB::table('authors')->update(['name' => 'Fresh morph']);
            DB::table('posts')->update(['title' => 'Fresh post']);
            $cache = Cache::getFacadeRoot();
            Cache::shouldReceive('store')->never();
            Cache::shouldReceive('driver')->never();

            try {
                $post = $query->effect()->sole();
                $this->assertSame('Fresh morph', $post->subject->name);
                $this->assertSame('Fresh post', $post->subject->posts->sole()->title);
            } finally {
                Cache::swap($cache);
            }
        } finally {
            DB::rollBack();
        }

        $this->assertSame('Original author', $query->effect()->sole()->subject->name);
    }

    public function test_native_morph_constraints_are_preserved_while_guarding_each_type_query(): void
    {
        $model = new class() extends PlainPost
        {
            public function subject(): MorphTo
            {
                return $this->morphTo();
            }
        };
        DB::table('posts')->update(['subject_type' => AutomaticallyCachedAuthor::class, 'subject_id' => 42]);
        $query = $this->createQuery('result', model: $model::class)->withRelations([
            'subject' => function (MorphTo $relation): void {
                $relation->constrain([
                    AutomaticallyCachedAuthor::class => fn (Builder $author) => $author->where('name', '!=', 'Excluded')->cacheFor(600),
                ]);
            },
        ]);
        $this->assertSame('Original author', $query->effect()->sole()->subject->name);
        DB::beginTransaction();

        try {
            DB::table('authors')->update(['name' => 'Excluded']);
            $this->assertNull($query->effect()->sole()->subject);
        } finally {
            DB::rollBack();
        }
    }

    #[DataProvider('morphStrategies')]
    public function test_mapped_morph_types_preserve_projections_constraints_and_nested_relations_during_bypass(string $strategy, bool $nativeMorph): void
    {
        $modelClass = AutomaticallyCachedPost::class;

        if ($nativeMorph) {
            $modelClass = (new class() extends AutomaticallyCachedPost
            {
                protected function newMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
                {
                    return new MorphTo($query, $parent, $foreignKey, $ownerKey, $type, $relation);
                }
            })::class;
        }

        $originalMap = Relation::morphMap();
        Relation::morphMap(['cached-author' => AutomaticallyCachedAuthor::class, 'cached-post' => AutomaticallyCachedPost::class], false);

        try {
            DB::table('posts')->where('id', 1)->update(['subject_type' => 'cached-author', 'subject_id' => 42]);
            DB::table('posts')->insert([
                ['id' => 2, 'author_id' => 42, 'title' => 'Second', 'published' => true, 'subject_type' => 'cached-post', 'subject_id' => 3],
                ['id' => 3, 'author_id' => 42, 'title' => 'Original target', 'published' => false, 'subject_type' => null, 'subject_id' => null],
            ]);
            $prepared = $this->createQuery($strategy, model: $modelClass)->withRelations([
                'subject' => function (MorphTo $relation): void {
                    $relation->select('id')->morphWith([
                        AutomaticallyCachedAuthor::class => ['posts'],
                        AutomaticallyCachedPost::class => ['author'],
                    ])->constrain([
                        AutomaticallyCachedAuthor::class => fn (Builder $query) => $query->addSelect('name')->where('name', '!=', 'Excluded')
                            ->withGlobalScope('enable-cache', fn (Builder $query) => $query->cacheFor(600))
                            ->beforeQuery(fn ($base) => $base->cacheFor(600)),
                        AutomaticallyCachedPost::class => fn (Builder $query) => $query->addSelect('title', 'author_id')->where('title', '!=', 'Excluded')
                            ->withGlobalScope('enable-cache', fn (Builder $query) => $query->cacheFor(600))
                            ->beforeQuery(fn ($base) => $base->cacheFor(600)),
                    ]);
                },
            ])->query()->whereIn('posts.id', [1, 2])->orderBy('posts.id');
            $query = $this->usingPreparedBuilder($strategy, $prepared);
            $cached = $query->effect();
            $this->assertSame(['id' => 42, 'name' => 'Original author'], $cached[0]->subject->getAttributes());
            $this->assertSame('Original author', $cached[0]->subject->name);
            $this->assertSame('Original target', $cached[1]->subject->title);
            DB::beginTransaction();
            $cache = Cache::getFacadeRoot();

            try {
                DB::table('authors')->update(['name' => 'Fresh author']);
                DB::table('posts')->where('id', 3)->update(['title' => 'Fresh target']);
                Cache::shouldReceive('store')->never();
                Cache::shouldReceive('driver')->never();

                for ($attempt = 0; $attempt < 2; $attempt++) {
                    $fresh = $query->effect();
                    $author = $fresh[0]->subject;
                    $post = $fresh[1]->subject;
                    $this->assertInstanceOf(AutomaticallyCachedAuthor::class, $author);
                    $this->assertInstanceOf(AutomaticallyCachedPost::class, $post);
                    $this->assertSame(['id' => 42, 'name' => 'Fresh author'], $author->getAttributes());
                    $this->assertSame(['id' => 3, 'title' => 'Fresh target', 'author_id' => 42], $post->getAttributes());
                    $this->assertSame('Fresh target', $author->posts->find(3)->title);
                    $this->assertSame('Fresh author', $post->author->name);
                }

                DB::table('authors')->update(['name' => 'Excluded']);
                $this->assertNull($query->effect()[0]->subject);
                DB::table('posts')->where('id', 3)->update(['title' => 'Excluded']);
                $this->assertNull($query->effect()[1]->subject);
            } finally {
                Cache::swap($cache);
                DB::rollBack();
            }

            $this->assertSame('Original author', $query->effect()[0]->subject->name);
            $this->assertSame('Original target', $query->effect()[1]->subject->title);
        } finally {
            Relation::morphMap($originalMap, false);
        }
    }

    #[DataProvider('strategies')]
    public function test_query_cache_morph_types_and_aliases_bypass_cache_for_each_type_and_nested_relation(string $strategy): void
    {
        $originalMap = Relation::morphMap();
        Relation::morphMap(['cached-author' => AutomaticallyCachedAuthor::class, 'cached-post' => AutomaticallyCachedPost::class], false);

        try {
            DB::table('posts')->where('id', 1)->update(['subject_type' => 'cached-author', 'subject_id' => 42]);
            DB::table('posts')->insert([
                ['id' => 2, 'author_id' => 42, 'title' => 'Second', 'published' => true, 'subject_type' => 'cached-post', 'subject_id' => 3],
                ['id' => 3, 'author_id' => 42, 'title' => 'Original target', 'published' => false, 'subject_type' => null, 'subject_id' => null],
            ]);
            $prepared = $this->createQuery($strategy)->withRelations([
                'subject' => fn (MorphTo $relation) => $relation->morphWith([
                    AutomaticallyCachedAuthor::class => ['posts'],
                    AutomaticallyCachedPost::class => ['author'],
                ]),
            ])->query()->whereIn('posts.id', [1, 2])->orderBy('posts.id');
            $query = $this->usingPreparedBuilder($strategy, $prepared);
            $this->assertSame('Original author', $query->effect()[0]->subject->name);
            $this->assertSame('Original target', $query->effect()[1]->subject->title);
            DB::beginTransaction();
            $cache = Cache::getFacadeRoot();

            try {
                DB::table('authors')->update(['name' => 'Fresh author']);
                DB::table('posts')->where('id', 3)->update(['title' => 'Fresh target']);
                Cache::shouldReceive('store')->never();
                Cache::shouldReceive('driver')->never();
                $fresh = $query->effect();
                $this->assertSame('Fresh author', $fresh[0]->subject->name);
                $this->assertSame('Fresh target', $fresh[0]->subject->posts->find(3)->title);
                $this->assertSame('Fresh target', $fresh[1]->subject->title);
                $this->assertSame('Fresh author', $fresh[1]->subject->author->name);
            } finally {
                Cache::swap($cache);
                DB::rollBack();
            }

            $this->assertSame('Original author', $query->effect()[0]->subject->name);
            $this->assertSame('Original target', $query->effect()[1]->subject->title);
        } finally {
            Relation::morphMap($originalMap, false);
        }
    }

    #[DataProvider('strategies')]
    public function test_buffered_soft_delete_calls_are_preserved_for_morph_types_during_bypass(string $strategy): void
    {
        Schema::table('authors', fn (Blueprint $table) => $table->softDeletes());
        $author = new class() extends AutomaticallyCachedAuthor
        {
            use SoftDeletes;
        };
        DB::table('authors')->update(['deleted_at' => '2026-01-01 00:00:00']);
        DB::table('posts')->update(['subject_type' => $author::class, 'subject_id' => 42]);
        $query = $this->createQuery($strategy)->withRelations([
            'subject' => fn (MorphTo $relation) => $relation->withTrashed()->select('id', 'name'),
        ]);
        $this->assertSame('Original author', $query->effect()->sole()->subject->name);
        DB::beginTransaction();
        $cache = Cache::getFacadeRoot();

        try {
            DB::table('authors')->update(['name' => 'Fresh deleted author']);
            Cache::shouldReceive('store')->never();
            Cache::shouldReceive('driver')->never();
            $fresh = $query->effect()->sole()->subject;
            $this->assertInstanceOf($author::class, $fresh);
            $this->assertSame(['id' => 42, 'name' => 'Fresh deleted author'], $fresh->getAttributes());
        } finally {
            Cache::swap($cache);
            DB::rollBack();
        }

        $this->assertSame('Original author', $query->effect()->sole()->subject->name);
    }

    #[DataProvider('strategies')]
    public function test_pagination_totals_and_every_page_bypass_cache(string $strategy): void
    {
        $query = $this->createQuery($strategy, 'paginate')->withRelations('author');
        $this->assertSame(1, $query->effect()->total());
        DB::beginTransaction();

        try {
            DB::table('posts')->insert(['id' => 2, 'author_id' => 42, 'title' => 'Second', 'published' => true]);
            DB::table('authors')->update(['name' => 'Fresh author']);
            $first = $query->effect();
            $second = $query->page(2)->effect();
            $this->assertSame(2, $first->total());
            $this->assertSame(2, $second->total());
            $this->assertSame('Second', $second->items()[0]->title);
            $this->assertSame('Fresh author', $second->items()[0]->author->name);
        } finally {
            DB::rollBack();
        }

        $this->assertSame(1, $query->page(1)->effect()->total());
        $this->assertCount(0, $query->page(2)->effect()->items());
    }

    #[DataProvider('strategies')]
    public function test_manual_bypass_remains_active_after_a_transaction(string $strategy): void
    {
        $query = $this->createQuery($strategy);
        $query->effect();
        $query->withoutCache();
        DB::transaction(fn () => $this->assertSame('Original', $query->effect()->sole()->title));
        DB::table('posts')->update(['title' => 'Fresh']);
        $this->assertSame('Fresh', $query->effect()->sole()->title);
        $this->assertSame('Original', $query->withoutCache(false)->effect()->sole()->title);
    }

    #[DataProvider('strategies')]
    public function test_queries_without_the_marker_keep_the_existing_transaction_cache_behavior(string $strategy): void
    {
        $query = $strategy === 'result' ? new Queries\CachedAllPostsQuery : new Queries\BuilderCachedAllPostsQuery;
        $query->effect();
        DB::beginTransaction();

        try {
            DB::table('posts')->update(['title' => 'Fresh']);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->assertSame('Original', $query->effect()->sole()->title);
            $this->assertCount(0, DB::getQueryLog());
        } finally {
            DB::rollBack();
        }
    }

    #[DataProvider('strategies')]
    public function test_transactions_on_the_default_connection_do_not_disable_another_connections_cache(string $strategy): void
    {
        $other = $this->createOtherConnection();
        $model = new class() extends AutomaticallyCachedPost
        {
            protected $connection = 'other';
        };
        $query = $this->createQuery($strategy, model: $model::class);
        $this->assertSame('Other original', $query->effect()->sole()->title);
        DB::beginTransaction();

        try {
            $other->table('posts')->update(['title' => 'Other changed']);
            $other->enableQueryLog();
            $other->flushQueryLog();
            $this->assertSame('Other original', $query->effect()->sole()->title);
            $this->assertCount(0, $other->getQueryLog());
        } finally {
            DB::rollBack();
        }
    }

    #[DataProvider('strategies')]
    public function test_a_transaction_on_the_queries_own_nondefault_connection_bypasses_cache(string $strategy): void
    {
        $other = $this->createOtherConnection();
        $model = new class() extends AutomaticallyCachedPost
        {
            protected $connection = 'other';
        };
        $query = $this->createQuery($strategy, model: $model::class);
        $this->assertSame('Other original', $query->effect()->sole()->title);
        $other->beginTransaction();

        try {
            $this->assertSame(0, DB::transactionLevel());
            $other->table('posts')->update(['title' => 'Other uncommitted']);
            $this->assertSame('Other uncommitted', $query->effect()->sole()->title);
        } finally {
            $other->rollBack();
        }

        $this->assertSame('Other original', $query->effect()->sole()->title);
    }

    #[DataProvider('strategies')]
    public function test_the_actual_builder_connection_is_used_after_the_default_connection_changes(string $strategy): void
    {
        $this->createOtherConnection();
        $prepared = $this->createQuery($strategy)->query();
        $query = $this->usingPreparedBuilder($strategy, $prepared);
        $query->effect();
        $connection = DB::connection('testing');
        DB::setDefaultConnection('other');
        $connection->beginTransaction();

        try {
            $connection->table('posts')->update(['title' => 'Actual connection']);
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame('Actual connection', $query->effect()->sole()->title);
        } finally {
            $connection->rollBack();
            DB::setDefaultConnection('testing');
        }

        $this->assertSame('Original', $query->effect()->sole()->title);
    }

    #[DataProvider('strategies')]
    public function test_root_bypass_is_inherited_by_relations_using_a_different_connection(string $strategy): void
    {
        $other = $this->createOtherConnection();
        $query = $this->createQuery($strategy)->withRelations('crossConnectionAuthor');
        $this->assertSame('Other author', $query->effect()->sole()->crossConnectionAuthor->name);
        DB::beginTransaction();

        try {
            $this->assertSame(0, $other->transactionLevel());
            $other->table('authors')->update(['name' => 'Fresh other author']);
            $this->assertSame('Fresh other author', $query->effect()->sole()->crossConnectionAuthor->name);
        } finally {
            DB::rollBack();
        }

        $this->assertSame('Other author', $query->effect()->sole()->crossConnectionAuthor->name);
    }

    public static function preparationStrategies(): iterable
    {
        foreach (['result', 'builder'] as $strategy) {
            foreach (['scope', 'callback'] as $stage) {
                yield "$strategy / $stage" => [$strategy, $stage];
            }
        }
    }

    #[DataProvider('preparationStrategies')]
    public function test_scopes_and_callbacks_run_once_before_transaction_state_is_checked(string $strategy, string $stage): void
    {
        $prepared = $this->createQuery($strategy)->query();
        $query = $this->usingPreparedBuilder($strategy, $prepared);
        $query->effect();
        $calls = 0;
        $start = function () use (&$calls): void {
            if (++$calls === 1) {
                DB::beginTransaction();
                DB::table('posts')->update(['title' => 'Started during preparation']);
            }
        };

        if ($stage === 'scope') {
            $prepared->withGlobalScope('start-transaction', $start);
        } else {
            $prepared->getQuery()->beforeQuery($start);
        }

        try {
            $this->assertSame('Started during preparation', $query->effect()->sole()->title);
            $this->assertSame(1, $calls);
        } finally {
            DB::rollBack();
        }

        $this->assertSame('Original', $query->effect()->sole()->title);
        $this->assertSame(2, $calls);
    }

    #[DataProvider('strategies')]
    public function test_exception_rollbacks_do_not_leave_the_query_in_bypass_mode(string $strategy): void
    {
        $query = $this->createQuery($strategy);
        $query->effect();

        try {
            DB::transaction(function () use ($query): void {
                DB::table('posts')->update(['title' => 'Before exception']);
                $this->assertSame('Before exception', $query->effect()->sole()->title);

                throw new RuntimeException('Roll back');
            });
            $this->fail('The transaction must throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Roll back', $exception->getMessage());
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame('Original', $query->effect()->sole()->title);
        $this->assertCount(0, DB::getQueryLog());
    }

    #[DataProvider('strategies')]
    public function test_static_result_uses_the_transaction_policy(string $strategy): void
    {
        $class = $this->createQuery($strategy)::class;
        $this->assertSame('Original', $class::result()->sole()->title);
        DB::beginTransaction();

        try {
            DB::table('posts')->update(['title' => 'Static fresh']);
            $this->assertSame('Static fresh', $class::result()->sole()->title);
        } finally {
            DB::rollBack();
        }

        $this->assertSame('Original', $class::result()->sole()->title);
    }

    #[DataProvider('strategies')]
    public function test_runtime_cache_settings_cannot_override_the_policy_and_are_preserved(string $strategy): void
    {
        $query = $this->createQuery($strategy);
        $expiration = new \DateTime('+10 minutes');
        $query->setCacheFor($expiration)->effect();
        DB::beginTransaction();

        try {
            DB::table('posts')->update(['title' => 'Fresh']);
            $query->withoutCache(false);
            $this->assertSame('Fresh', $query->effect()->sole()->title);
            $this->assertSame($expiration, $query->cacheFor());
            $query->setCacheFor(0);
            $this->assertSame('Fresh', $query->effect()->sole()->title);
        } finally {
            DB::rollBack();
        }

        DB::table('posts')->update(['title' => 'After rollback']);
        $this->assertSame(0, $query->cacheFor());
        $this->assertSame('After rollback', $query->effect()->sole()->title);
        $this->assertSame('Original', $query->setCacheFor($expiration)->effect()->sole()->title);
    }

    private function createOtherConnection(): Connection
    {
        config()->set('database.connections.other', config('database.connections.testing'));
        $schema = Schema::connection('other');
        $schema->create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        $schema->create('posts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('author_id')->nullable();
            $table->string('title');
            $table->boolean('published');
        });
        $connection = DB::connection('other');
        $connection->table('authors')->insert(['id' => 42, 'name' => 'Other author']);
        $connection->table('posts')->insert(['id' => 1, 'author_id' => 42, 'title' => 'Other original', 'published' => true]);

        return $connection;
    }

    private function usingPreparedBuilder(string $strategy, Builder $prepared): CachedQuery|BuilderCachedQuery
    {
        return $strategy === 'result'
            ? new class($prepared) extends Queries\TransactionCachedQuery
            {
                use Results\QueryResultAll;

                public function __construct(private readonly Builder $prepared) {}

                public function query(): BuilderContract
                {
                    return $this->prepared;
                }
            }
        : new class($prepared) extends Queries\TransactionBuilderCachedQuery
        {
            use Results\QueryResultAll;

            public function __construct(private readonly Builder $prepared) {}

            public function query(): BuilderContract
            {
                return $this->prepared;
            }
        };
    }

    private function expectedResult(string $kind, string $title): mixed
    {
        return match ($kind) {
            'count', 'aggregate' => 1,
            'exists' => true,
            'all', 'collection', 'pluck', 'paginate', 'simple', 'cursor' => [$title],
            default => $title,
        };
    }

    private function normalize(string $kind, mixed $result): mixed
    {
        return match ($kind) {
            'all', 'collection' => $result->pluck('title')->all(),
            'first', 'firstOrFail', 'sole' => $result?->title,
            'pluck' => $result->all(),
            'paginate', 'simple', 'cursor' => array_map(fn (Model $model) => $model->title, $result->items()),
            default => $result,
        };
    }

    private function createQuery(string $strategy, string $kind = 'all', ?string $model = null): CachedQuery|BuilderCachedQuery
    {
        $query = match ([$strategy, $kind]) {
            ['result', 'all'] => new class($model) extends Queries\TransactionCachedQuery
            {
                use Results\QueryResultAll;
            },
            ['result', 'collection'] => new class($model) extends Queries\TransactionCachedQuery
            {
                use Results\QueryResultCollection;
            },
            ['result', 'first'] => new class($model) extends Queries\TransactionCachedQuery
            {
                use Results\QueryResultFirst;
            },
            ['result', 'firstOrFail'] => new class($model) extends Queries\TransactionCachedQuery
            {
                use Results\QueryResultFirstOrFail;
            },
            ['result', 'sole'] => new class($model) extends Queries\TransactionCachedQuery
            {
                use Results\QueryResultSole;
            },
            ['result', 'exists'] => new class($model) extends Queries\TransactionCachedQuery
            {
                use Results\QueryResultExists;
            },
            ['result', 'count'] => new class($model) extends Queries\TransactionCachedQuery
            {
                use Results\QueryResultCount;
            },
            ['result', 'value'] => new class($model) extends Queries\TransactionCachedQuery
            {
                use Results\QueryResultValue;
            },
            ['result', 'pluck'] => new class($model) extends Queries\TransactionCachedQuery
            {
                use Results\QueryResultPluck;
            },
            ['result', 'aggregate'] => new class($model) extends Queries\TransactionCachedQuery
            {
                use Results\QueryResultAggregate;
            },
            ['result', 'paginate'] => new class($model) extends Queries\TransactionCachedQuery implements WithPaginatedResult
            {
                use Results\QueryResultPaginated;
            },
            ['result', 'simple'] => new class($model) extends Queries\TransactionCachedQuery implements WithPaginatedResult
            {
                use Results\QueryResultSimplePaginated;
            },
            ['result', 'cursor'] => new class($model) extends Queries\TransactionCachedQuery implements WithCursorPaginatedResult
            {
                use Results\QueryResultCursorPaginated;
            },
            ['builder', 'all'] => new class($model) extends Queries\TransactionBuilderCachedQuery
            {
                use Results\QueryResultAll;
            },
            ['builder', 'collection'] => new class($model) extends Queries\TransactionBuilderCachedQuery
            {
                use Results\QueryResultCollection;
            },
            ['builder', 'first'] => new class($model) extends Queries\TransactionBuilderCachedQuery
            {
                use Results\QueryResultFirst;
            },
            ['builder', 'firstOrFail'] => new class($model) extends Queries\TransactionBuilderCachedQuery
            {
                use Results\QueryResultFirstOrFail;
            },
            ['builder', 'sole'] => new class($model) extends Queries\TransactionBuilderCachedQuery
            {
                use Results\QueryResultSole;
            },
            ['builder', 'exists'] => new class($model) extends Queries\TransactionBuilderCachedQuery
            {
                use Results\QueryResultExists;
            },
            ['builder', 'count'] => new class($model) extends Queries\TransactionBuilderCachedQuery
            {
                use Results\QueryResultCount;
            },
            ['builder', 'value'] => new class($model) extends Queries\TransactionBuilderCachedQuery
            {
                use Results\QueryResultValue;
            },
            ['builder', 'pluck'] => new class($model) extends Queries\TransactionBuilderCachedQuery
            {
                use Results\QueryResultPluck;
            },
            ['builder', 'aggregate'] => new class($model) extends Queries\TransactionBuilderCachedQuery
            {
                use Results\QueryResultAggregate;
            },
            ['builder', 'paginate'] => new class($model) extends Queries\TransactionBuilderCachedQuery implements WithPaginatedResult
            {
                use Results\QueryResultPaginated;
            },
            ['builder', 'simple'] => new class($model) extends Queries\TransactionBuilderCachedQuery implements WithPaginatedResult
            {
                use Results\QueryResultSimplePaginated;
            },
            ['builder', 'cursor'] => new class($model) extends Queries\TransactionBuilderCachedQuery implements WithCursorPaginatedResult
            {
                use Results\QueryResultCursorPaginated;
            },
        };

        if ($kind === 'collection') {
            $query->limit(10);
        } elseif (in_array($kind, ['value', 'pluck', 'aggregate'], true)) {
            $query->withResultColumn($kind === 'aggregate' ? 'posts.id' : 'posts.title');

            if ($kind === 'aggregate') {
                $query->withAggregateFunction('sum');
            }
        } elseif (in_array($kind, ['paginate', 'simple'], true)) {
            $query->perPage(1)->page(1);
        } elseif ($kind === 'cursor') {
            $query->perPage(1)->cursor('');
        }

        return $query;
    }
}
