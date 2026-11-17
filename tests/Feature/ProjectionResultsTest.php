<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Attributes\CacheStore;
use Atldays\Sculptor\Enums\AggregateFunction;
use Atldays\Sculptor\Events\ResultExecuted;
use Atldays\Sculptor\Tests\Fixtures\Filters\{CallbackFilter, PublishedFilter};
use Atldays\Sculptor\Tests\Fixtures\Queries;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\{Builder as QueryBuilder, Expression};
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\{Cache, DB, Event};
use PHPUnit\Framework\Attributes\DataProvider;

class ProjectionResultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('posts')->insert([
            ['title' => 'alpha', 'published' => true, 'created_at' => '2026-01-02 03:04:05'],
            ['title' => 'beta', 'published' => false, 'created_at' => null],
            ['title' => 'beta', 'published' => true, 'created_at' => null],
        ]);
    }

    public static function queries(): iterable
    {
        foreach (['' => 'plain', 'Cached' => 'result cache', 'BuilderCached' => 'builder cache'] as $prefix => $strategy) {
            foreach (['Value', 'Pluck', 'Aggregate'] as $kind) {
                yield $strategy . ' ' . $kind => [Queries::class . '\\' . $prefix . $kind . 'ProjectionQuery', $kind];
            }
        }
    }

    public static function cachedQueries(): iterable
    {
        foreach (self::queries() as $name => $arguments) {
            if (!str_starts_with($name, 'plain ')) {
                yield $name => $arguments;
            }
        }
    }

    public static function pluckQueries(): iterable
    {
        foreach (self::queries() as $name => [$class, $kind]) {
            if ($kind === 'Pluck') {
                yield $name => [$class];
            }
        }
    }

    public static function valueQueries(): iterable
    {
        foreach (self::queries() as $name => [$class, $kind]) {
            if ($kind === 'Value') {
                yield $name => [$class];
            }
        }
    }

    #[DataProvider('valueQueries')]
    public function test_value_preserves_false_zero_and_date_casts(string $class): void
    {
        $false = $class::make()->withResultColumn('posts.published')
            ->addFilter(new CallbackFilter(fn (Builder $query) => $query->where('posts.id', 2)));
        $this->assertFalse($false->effect());
        $this->assertFalse($false->effect());

        $zero = $class::make()->withResultColumn(new Expression('id - 1'));
        $this->assertSame(0, $zero->effect());
        $this->assertSame(0, $zero->effect());

        $date = $class::make()->withResultColumn('posts.created_at');
        $this->assertInstanceOf(Carbon::class, $date->effect());
        $this->assertEquals($date->effect(), $date->effect());
    }

    public static function aggregateQueries(): iterable
    {
        foreach (self::queries() as $name => [$class, $kind]) {
            if ($kind === 'Aggregate') {
                yield $name => [$class];
            }
        }
    }

    public static function nullableQueries(): iterable
    {
        foreach (self::cachedQueries() as $name => [$class, $kind]) {
            if ($kind === 'Value') {
                foreach ([false, true] as $missing) {
                    yield $name . ($missing ? ' missing' : ' null row') => [$class, null, $missing];
                }
            } elseif ($kind === 'Aggregate') {
                foreach (['avg', 'min', 'max'] as $function) {
                    yield $name . ' ' . $function => [$class, $function, false];
                }
            }
        }
    }

    #[DataProvider('queries')]
    public function test_attribute_results_preserve_types_and_apply_filters(string $class, string $kind): void
    {
        $query = $class::make()->addFilter(new PublishedFilter);
        $this->assertSame(match ($kind) {
            'Value' => 'ALPHA',
            'Pluck' => [1 => 'ALPHA', 3 => 'BETA'],
            'Aggregate' => 4,
        }, $this->snapshot($query->effect()));
    }

    #[DataProvider('queries')]
    public function test_runtime_column_overrides_attribute_without_mutating_select_configuration(string $class, string $kind): void
    {
        $query = $class::make()->withSelect(['posts.published'])->withRelations('not_a_real_relation')->withResultColumn('posts.id');
        DB::enableQueryLog();

        $this->assertSame(match ($kind) {
            'Value' => 1,
            'Pluck' => [1 => 1, 2 => 2, 3 => 3],
            'Aggregate' => 6,
        }, $this->snapshot($query->effect()));
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame(['posts.published'], $query->select());

        if ($kind !== 'Aggregate') {
            $this->assertStringNotContainsString('*', DB::getQueryLog()[0]['query']);
            $this->assertStringNotContainsString('published', DB::getQueryLog()[0]['query']);
        }
    }

    #[DataProvider('queries')]
    public function test_expressions_are_supported_and_distinguish_cache_entries(string $class, string $kind): void
    {
        $query = $class::make();
        $first = $kind === 'Value' ? 'id' : 'id + 1' . ($kind === 'Pluck' ? ' as projected' : '');
        $second = $kind === 'Value' ? 'published' : 'id + 2' . ($kind === 'Pluck' ? ' as projected' : '');

        if ($kind === 'Pluck') {
            $query->withPluckKey(null);
        }

        $firstValue = $this->snapshot($query->withResultColumn(new Expression($first))->effect());
        $secondValue = $this->snapshot($query->withResultColumn(new Expression($second))->effect());
        $this->assertNotSame($firstValue, $secondValue);
        $this->assertSame($firstValue, $this->snapshot($query->withResultColumn(new Expression($first))->effect()));

        if ($kind === 'Aggregate') {
            $this->assertSame(9, $firstValue);
            $this->assertSame(12, $secondValue);
        }
    }

    #[DataProvider('pluckQueries')]
    public function test_pluck_keys_can_be_changed_or_disabled_and_duplicate_keys_follow_laravel(string $class): void
    {
        $query = $class::make();
        $this->assertSame([1 => 'ALPHA', 2 => 'BETA', 3 => 'BETA'], $query->effect()->all());
        $this->assertSame(['ALPHA', 'BETA', 'BETA'], $query->withPluckKey(null)->effect()->all());
        $this->assertSame([1 => 'BETA', 0 => 'BETA'], $query->withPluckKey('posts.published')->effect()->all());
        $this->assertSame([1 => 'ALPHA', 2 => 'BETA', 3 => 'BETA'], $query->withPluckKey('posts.id')->effect()->all());
        $this->assertSame(['ALPHA', 'BETA'], $query->limit(2)->withPluckKey(null)->effect()->all());
    }

    #[DataProvider('pluckQueries')]
    public function test_pluck_casts_and_dates_are_preserved(string $class): void
    {
        $query = $class::make();
        $this->assertSame([1 => true, 2 => false, 3 => true], $query->withResultColumn('posts.published')->effect()->all());
        $dates = $query->withResultColumn('posts.created_at')->effect();
        $this->assertInstanceOf(Carbon::class, $dates[1]);
        $this->assertNull($dates[2]);
        $this->assertEquals($dates, $query->effect());
    }

    #[DataProvider('aggregateQueries')]
    public function test_aggregate_functions_and_columns_match_native_results_on_reused_queries(string $class): void
    {
        $expected = [];

        foreach (AggregateFunction::cases() as $function) {
            foreach (['posts.id', 'posts.author_id'] as $column) {
                $expected[$function->value][$column] = DB::table('posts')->{$function->value}($column);
            }
        }

        $query = $class::make();

        for ($i = 0; $i < 2; $i++) {
            foreach ($expected as $function => $columns) {
                foreach ($columns as $column => $value) {
                    $this->assertSame($value, $query->withAggregateFunction($function)->withResultColumn($column)->effect());
                }
            }
        }

        $this->assertSame(6, $query->withAggregateFunction(AggregateFunction::Sum)->withResultColumn('posts.id')->limit(1)->effect());
    }

    #[DataProvider('cachedQueries')]
    public function test_cache_hits_and_tag_invalidation_for_each_new_result(string $class, string $kind): void
    {
        $query = $class::make()->setCacheTags('projections');
        DB::enableQueryLog();
        $first = $this->snapshot($query->effect());
        $this->assertSame($first, $this->snapshot($query->effect()));
        $this->assertSame($first, $this->snapshot($class::make()->setCacheTags('projections')->effect()));
        $this->assertCount(1, DB::getQueryLog());
        DB::table('posts')->delete();
        DB::flushQueryLog();
        $this->assertSame($first, $this->snapshot($query->effect()));
        $this->assertCount(0, DB::getQueryLog());
        Cache::store('array')->tags('projections')->flush();
        $this->assertSame($kind === 'Pluck' ? [] : ($kind === 'Aggregate' ? 0 : null), $this->snapshot($query->effect()));
        $this->assertCount(1, DB::getQueryLog());
    }

    #[DataProvider('cachedQueries')]
    public function test_without_cache_preserves_existing_entries(string $class, string $kind): void
    {
        $query = $class::make();
        $first = $this->snapshot($query->effect());
        DB::table('posts')->delete();
        $query->withoutCache();
        DB::enableQueryLog();
        $empty = $kind === 'Pluck' ? [] : ($kind === 'Aggregate' ? 0 : null);
        $this->assertSame($empty, $this->snapshot($query->effect()));
        $this->assertSame($empty, $this->snapshot($query->effect()));
        $this->assertCount(2, DB::getQueryLog());
        $this->assertSame($first, $this->snapshot($query->withoutCache(false)->effect()));
        $this->assertCount(2, DB::getQueryLog());
    }

    #[DataProvider('cachedQueries')]
    public function test_non_positive_ttls_bypass_reads_and_writes(string $class, string $kind): void
    {
        foreach ([0, -1] as $ttl) {
            $class::flushBaseCache();
            $query = $class::make()->setCacheFor($ttl);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $first = $this->snapshot($query->effect());
            $this->assertSame($first, $this->snapshot($query->effect()));
            $this->assertCount(2, DB::getQueryLog());
            $query->setCacheFor(60)->effect();
            $this->assertCount(3, DB::getQueryLog());
        }
    }

    #[DataProvider('nullableQueries')]
    public function test_null_results_are_cached_and_invalidated(string $class, ?string $function, bool $missing): void
    {
        DB::table('authors')->insert(['id' => 1, 'name' => 'Author']);

        if ($missing) {
            DB::table('posts')->delete();
        }

        $make = function () use ($class, $function) {
            $query = $class::make()->withResultColumn('posts.author_id');

            return $function === null ? $query : $query->withAggregateFunction($function);
        };
        DB::enableQueryLog();
        $this->assertNull($make()->effect());
        $this->assertNull($make()->effect());
        $this->assertCount(1, DB::getQueryLog());

        if ($missing) {
            DB::table('posts')->insert(['title' => 'later', 'published' => true, 'author_id' => 1]);
        } else {
            DB::table('posts')->update(['author_id' => 1]);
        }

        $expected = $function === null ? 1 : DB::table('posts')->{$function}('author_id');
        DB::flushQueryLog();
        $this->assertNull($make()->effect());
        $this->assertCount(0, DB::getQueryLog());
        $class::flushBaseCache();
        $this->assertSame($expected, $make()->effect());
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_null_result_cache_survives_serialization(): void
    {
        config()->set('cache.stores.serialized', ['driver' => 'array', 'serialize' => true]);
        $query = new #[CacheStore('serialized')] class extends Queries\CachedValueProjectionQuery {};
        $query->withResultColumn('posts.author_id');
        DB::enableQueryLog();
        $this->assertNull($query->effect());
        $this->assertNull($query->effect());
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_same_sql_with_different_pluck_keys_has_independent_result_cache_entries(): void
    {
        foreach ([Queries\CachedPluckProjectionQuery::class, Queries\BuilderCachedPluckProjectionQuery::class] as $class) {
            $query = $class::make()->withResultColumn('posts.id')->withPluckKey(null);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->assertSame([1, 2, 3], $query->effect()->all());
            $this->assertSame([1 => 1, 2 => 2, 3 => 3], $query->withPluckKey('posts.id')->effect()->all());
            $this->assertSame([1, 2, 3], $query->withPluckKey(null)->effect()->all());
            $this->assertCount(2, DB::getQueryLog());
            $this->assertSame(DB::getQueryLog()[0]['query'], DB::getQueryLog()[1]['query']);
        }
    }

    #[DataProvider('queries')]
    public function test_result_events_are_dispatched_for_all_operations_and_cache_hits(string $class, string $kind): void
    {
        Event::fake([ResultExecuted::class]);
        $class::result();
        $class::result();
        Event::assertDispatchedTimes(ResultExecuted::class, 2);
        Event::assertDispatched(ResultExecuted::class, fn (ResultExecuted $event) => $event->name === $class);
    }

    public static function lockedQueries(): iterable
    {
        foreach (self::cachedQueries() as $name => [$class, $kind]) {
            foreach (['scope', 'callback'] as $mode) {
                yield $name . ' ' . $mode => [$class, $kind, $mode];
            }
        }
    }

    #[DataProvider('queries')]
    public function test_scopes_and_before_callbacks_run_once_for_the_effective_projection(string $class, string $kind): void
    {
        $scopes = 0;
        $callbacks = 0;
        $query = $class::make()->addFilter(new CallbackFilter(function (Builder $query) use (&$scopes, &$callbacks) {
            return $query->withGlobalScope('projection', function (Builder $query) use (&$scopes) {
                $scopes++;
                $query->where('published', true)->with('not_a_real_relation');
            })->beforeQuery(function (QueryBuilder $query) use (&$callbacks) {
                $callbacks++;
                $query->where('title', 'alpha');
            });
        }));
        DB::enableQueryLog();

        for ($i = 0; $i < 2; $i++) {
            $this->assertSame(match ($kind) {
                'Value' => 'ALPHA',
                'Pluck' => [1 => 'ALPHA'],
                'Aggregate' => 1,
            }, $this->snapshot($query->effect()));
        }

        $this->assertSame(2, $scopes);
        $this->assertSame(2, $callbacks);
        $this->assertCount(method_exists($query, 'getCache') ? 1 : 2, DB::getQueryLog());
    }

    #[DataProvider('lockedQueries')]
    public function test_locks_added_by_scopes_and_callbacks_bypass_old_cache_entries(string $class, string $kind, string $mode): void
    {
        $first = $this->snapshot($class::make()->effect());
        DB::table('posts')->delete();
        $query = $class::make()->addFilter(new CallbackFilter(function (Builder $query) use ($mode) {
            return $mode === 'scope'
                ? $query->withGlobalScope('locking', fn (Builder $query) => $query->lockForUpdate())
                : $query->beforeQuery(fn (QueryBuilder $query) => $query->lockForUpdate());
        }));
        DB::enableQueryLog();

        for ($i = 0; $i < 2; $i++) {
            $this->assertSame($kind === 'Pluck' ? [] : ($kind === 'Aggregate' ? 0 : null), $this->snapshot($query->effect()));
        }

        $this->assertCount(2, DB::getQueryLog());
        $this->assertSame($first, $this->snapshot($class::make()->effect()));
        $this->assertCount(2, DB::getQueryLog());
    }

    #[DataProvider('aggregateQueries')]
    public function test_grouped_having_distinct_and_date_aggregates_preserve_native_selection_and_types(string $class): void
    {
        $native = DB::table('posts')->selectRaw('sum(id) as total')->groupBy('published')->havingRaw('count(*) > ?', [1])->sum('total');
        $query = $class::make()->withResultColumn('total')->addFilter(new CallbackFilter(fn (Builder $query) => $query
            ->select([])->selectRaw('sum(id) as total')->groupBy('published')->havingRaw('count(*) > ?', [1])));
        $this->assertSame($native, $query->effect());
        $this->assertSame($native, $query->effect());

        $query = $class::make()->withResultColumn('posts.published')->addFilter(new CallbackFilter(fn (Builder $query) => $query->distinct()));
        $this->assertSame(DB::table('posts')->distinct()->sum('published'), $query->effect());

        $query = $class::make()->withResultColumn('posts.created_at')->withAggregateFunction('max');
        $this->assertSame(DB::table('posts')->max('created_at'), $query->effect());
        $this->assertIsString($query->effect());
    }

    private function snapshot(mixed $value): mixed
    {
        return $value instanceof Collection ? $value->all() : $value;
    }
}
