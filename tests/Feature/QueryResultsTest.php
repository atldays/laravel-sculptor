<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Events\ResultExecuted;
use Atldays\Sculptor\Tests\Fixtures\Filters\PublishedFilter;
use Atldays\Sculptor\Tests\Fixtures\Queries;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\{Cache, DB, Event};
use PHPUnit\Framework\Attributes\DataProvider;

class QueryResultsTest extends TestCase
{
    public static function resultQueries(): iterable
    {
        foreach (['' => 'plain', 'Cached' => 'result cache', 'BuilderCached' => 'builder cache'] as $prefix => $strategy) {
            foreach (['All' => 'all', 'FirstOrFail' => 'firstOrFail', 'Exists' => 'exists', 'Count' => 'count'] as $name => $kind) {
                yield $strategy . ' ' . $kind => [Queries::class . '\\' . $prefix . $name . 'PostsQuery', $kind];
            }
        }
    }

    public static function cachedResultQueries(): iterable
    {
        foreach (self::resultQueries() as $name => $arguments) {
            if (!str_starts_with($name, 'plain ')) {
                yield $name => $arguments;
            }
        }
    }

    public static function allQueries(): iterable
    {
        foreach (self::resultQueries() as $name => $arguments) {
            if ($arguments[1] === 'all') {
                yield $name => [$arguments[0]];
            }
        }
    }

    public static function firstOrFailQueries(): iterable
    {
        foreach (self::resultQueries() as $name => $arguments) {
            if ($arguments[1] === 'firstOrFail') {
                yield $name => [$arguments[0]];
            }
        }
    }

    public static function scalarQueries(): iterable
    {
        foreach (self::resultQueries() as $name => $arguments) {
            if (in_array($arguments[1], ['exists', 'count'], true)) {
                yield $name => $arguments;
            }
        }
    }

    #[DataProvider('resultQueries')]
    public function test_results_respect_filters_and_preserve_native_types(string $queryClass, string $kind): void
    {
        DB::table('posts')->insert([
            ['title' => 'Visible', 'published' => true],
            ['title' => 'Hidden', 'published' => false],
        ]);

        $query = $queryClass::make()->addFilter(new PublishedFilter);

        $this->assertSame($this->expected($kind), $this->snapshot($query->effect(), $kind));
    }

    #[DataProvider('allQueries')]
    public function test_all_works_without_limit_and_respects_runtime_limit(string $queryClass): void
    {
        foreach (range(1, 4) as $index) {
            DB::table('posts')->insert(['title' => 'Post ' . $index, 'published' => true]);
        }

        $query = $queryClass::make();

        $this->assertCount(4, $query->effect());
        $this->assertCount(2, $query->limit(2)->effect());
        $this->assertCount(4, $query->withoutLimit()->effect());
    }

    public function test_all_respects_attribute_limit_and_explicitly_disabling_it(): void
    {
        foreach (range(1, 4) as $index) {
            DB::table('posts')->insert(['title' => 'Post ' . $index, 'published' => true]);
        }

        $query = Queries\AttributeAllPostsQuery::make();

        $this->assertCount(2, $query->effect());
        $this->assertCount(3, $query->limit(3)->effect());
        $this->assertCount(4, $query->withoutLimit()->effect());
    }

    #[DataProvider('firstOrFailQueries')]
    public function test_missing_first_result_throws_native_exception_and_dispatches_event(string $queryClass): void
    {
        Event::fake([ResultExecuted::class]);

        try {
            $queryClass::result();
            $this->fail('Expected ModelNotFoundException.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame($queryClass::make()->model(), $exception->getModel());
        }

        Event::assertDispatchedTimes(ResultExecuted::class, 1);
        Event::assertDispatched(ResultExecuted::class, fn (ResultExecuted $event) => $event->name === $queryClass);
    }

    #[DataProvider('scalarQueries')]
    public function test_scalar_results_use_native_sql_without_hydrating_models(string $queryClass, string $kind): void
    {
        DB::table('posts')->insert(['title' => 'Visible', 'published' => true]);
        $hydrated = 0;
        Event::listen('eloquent.retrieved: *', function () use (&$hydrated) {
            $hydrated++;
        });
        DB::enableQueryLog();

        $this->assertSame($this->expected($kind), $queryClass::make()->withRelations('not_a_real_relation')->effect());

        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString($kind === 'exists' ? 'exists(' : 'count(*)', strtolower($queries[0]['query']));
        $this->assertSame(0, $hydrated);
    }

    #[DataProvider('cachedResultQueries')]
    public function test_repeated_results_hit_cache_and_custom_tag_invalidates_it(string $queryClass, string $kind): void
    {
        DB::table('posts')->insert(['title' => 'Visible', 'published' => true]);
        $query = $queryClass::make()->setCacheTags('result-queries');

        $this->assertSame($this->expected($kind), $this->snapshot($query->effect(), $kind));

        DB::table('posts')->delete();
        DB::enableQueryLog();

        $this->assertSame($this->expected($kind), $this->snapshot($query->effect(), $kind));
        $this->assertSame($this->expected($kind), $this->snapshot($queryClass::make()->setCacheTags('result-queries')->effect(), $kind));
        $this->assertCount(0, DB::getQueryLog());

        Cache::store('array')->tags('result-queries')->flush();

        $this->assertEmptyResult($query, $kind);
        $this->assertCount(1, DB::getQueryLog());
    }

    #[DataProvider('cachedResultQueries')]
    public function test_without_cache_bypasses_reads_and_writes_and_retains_existing_entries(string $queryClass, string $kind): void
    {
        DB::table('posts')->insert(['title' => 'Visible', 'published' => true]);
        $query = $queryClass::make();
        $this->assertSame($this->expected($kind), $this->snapshot($query->effect(), $kind));

        DB::table('posts')->delete();
        $query->withoutCache();
        DB::enableQueryLog();

        $this->assertEmptyResult($query, $kind);
        $this->assertEmptyResult($query, $kind);
        $this->assertCount(2, DB::getQueryLog());

        $this->assertSame($this->expected($kind), $this->snapshot($query->withoutCache(false)->effect(), $kind));
        $this->assertCount(2, DB::getQueryLog());
    }

    #[DataProvider('cachedResultQueries')]
    public function test_zero_ttl_bypasses_cache_instead_of_caching_forever(string $queryClass, string $kind): void
    {
        DB::table('posts')->insert(['title' => 'Visible', 'published' => true]);
        $query = $queryClass::make()->setCacheFor(0);
        $this->assertSame($this->expected($kind), $this->snapshot($query->effect(), $kind));

        DB::table('posts')->delete();

        $this->assertEmptyResult($query, $kind);
        $this->assertEmptyResult($query->setCacheFor(120), $kind);
    }

    #[DataProvider('cachedResultQueries')]
    public function test_empty_collections_false_and_zero_are_cached_until_invalidated(string $queryClass, string $kind): void
    {
        if ($kind === 'firstOrFail') {
            $this->assertEmptyResult($queryClass::make(), $kind);
            DB::table('posts')->insert(['title' => 'Visible', 'published' => true]);
            $queryClass::flushCache();
            $this->assertSame('Visible', $queryClass::result()->title);

            return;
        }

        $query = $queryClass::make();
        $this->assertEmptyResult($query, $kind);
        DB::table('posts')->insert(['title' => 'Visible', 'published' => true]);
        DB::enableQueryLog();

        $this->assertEmptyResult($query, $kind);
        $this->assertCount(0, DB::getQueryLog());

        $queryClass::flushCache();

        $this->assertSame($this->expected($kind), $this->snapshot($query->effect(), $kind));
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_result_cache_does_not_cache_a_missing_model_exception(): void
    {
        $query = Queries\CachedFirstOrFailPostsQuery::make();
        $this->assertEmptyResult($query, 'firstOrFail');
        DB::table('posts')->insert(['title' => 'Visible', 'published' => true]);

        $this->assertSame('Visible', $query->effect()->title);
    }

    #[DataProvider('resultQueries')]
    public function test_static_result_dispatches_one_event_per_call_including_cache_hits(string $queryClass, string $kind): void
    {
        DB::table('posts')->insert(['title' => 'Visible', 'published' => true]);
        Event::fake([ResultExecuted::class]);

        $this->assertSame($this->expected($kind), $this->snapshot($queryClass::result(), $kind));
        $this->assertSame($this->expected($kind), $this->snapshot($queryClass::result(), $kind));

        Event::assertDispatchedTimes(ResultExecuted::class, 2);
        Event::assertDispatched(ResultExecuted::class, fn (ResultExecuted $event) => $event->name === $queryClass && $event->duration() >= 0);
    }

    private function expected(string $kind): mixed
    {
        return match ($kind) {
            'all' => ['Visible'],
            'firstOrFail' => 'Visible',
            'exists' => true,
            'count' => 1,
        };
    }

    private function snapshot(mixed $result, string $kind): mixed
    {
        return match ($kind) {
            'all' => $result->pluck('title')->all(),
            'firstOrFail' => $result->title,
            default => $result,
        };
    }

    private function assertEmptyResult(object $query, string $kind): void
    {
        if ($kind === 'firstOrFail') {
            try {
                $query->effect();
                $this->fail('Expected ModelNotFoundException.');
            } catch (ModelNotFoundException $exception) {
                $this->assertSame($query->model(), $exception->getModel());
            }

            return;
        }

        $this->assertSame(match ($kind) {
            'all' => [],
            'exists' => false,
            'count' => 0,
        }, $this->snapshot($query->effect(), $kind));
    }
}
