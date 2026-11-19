<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Events\ResultExecuted;
use Atldays\Sculptor\Tests\Fixtures\Filters\{CallbackFilter, PublishedFilter};
use Atldays\Sculptor\Tests\Fixtures\Queries;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Database\Eloquent\{Builder, ModelNotFoundException};
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\{Cache, DB, Event};
use PHPUnit\Framework\Attributes\DataProvider;

class AdditionalResultsTest extends TestCase
{
    public static function queries(): iterable
    {
        foreach (['' => 'plain', 'Cached' => 'result cache', 'BuilderCached' => 'builder cache'] as $prefix => $strategy) {
            foreach (['Sole', 'SimplePaginated', 'CursorPaginated'] as $kind) {
                yield $strategy . ' ' . $kind => [Queries::class . '\\' . $prefix . $kind . 'PostsQuery', $kind];
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

    public static function soleQueries(): iterable
    {
        foreach (self::queries() as $name => [$class, $kind]) {
            if ($kind === 'Sole') {
                yield $name => [$class];
            }
        }
    }

    #[DataProvider('soleQueries')]
    public function test_sole_preserves_native_exceptions_and_static_events(string $class): void
    {
        Event::fake([ResultExecuted::class]);

        try {
            $class::result();
            $this->fail('Expected missing model.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame($class::make()->model(), $exception->getModel());
        }

        DB::table('posts')->insert([
            ['title' => 'one', 'published' => true],
            ['title' => 'two', 'published' => false],
        ]);

        if (method_exists($class, 'flushCache')) {
            $class::flushCache();
        }

        try {
            $class::result();
            $this->fail('Expected multiple models.');
        } catch (MultipleRecordsFoundException $exception) {
            $this->assertSame(2, $exception->count);
        }

        Event::assertDispatchedTimes(ResultExecuted::class, 2);

        // sole() must still detect duplicates when a limit of one was configured.
        $this->expectException(MultipleRecordsFoundException::class);
        $class::make()->limit(1)->effect();
    }

    public function test_result_cache_does_not_store_either_sole_exception(): void
    {
        $query = Queries\CachedSolePostsQuery::make();

        try {
            $query->effect();
        } catch (ModelNotFoundException) {
        }

        DB::table('posts')->insert([['title' => 'one'], ['title' => 'two']]);

        try {
            $query->effect();
            $this->fail('Expected multiple models.');
        } catch (MultipleRecordsFoundException) {
        }

        DB::table('posts')->where('title', 'two')->delete();
        $this->assertSame('one', $query->effect()->title);
    }

    public function test_builder_cache_keeps_underlying_sole_rows_until_invalidated(): void
    {
        $class = Queries\BuilderCachedSolePostsQuery::class;
        DB::enableQueryLog();

        for ($i = 0; $i < 2; $i++) {
            try {
                $class::result();
                $this->fail('Expected cached empty selection.');
            } catch (ModelNotFoundException) {
                $this->assertCount(1, DB::getQueryLog());
            }
        }

        DB::table('posts')->insert([['title' => 'one'], ['title' => 'two']]);
        $class::flushCache();
        DB::flushQueryLog();

        for ($i = 0; $i < 2; $i++) {
            try {
                $class::result();
                $this->fail('Expected cached duplicate selection.');
            } catch (MultipleRecordsFoundException) {
                $this->assertCount(1, DB::getQueryLog());
            }
        }
    }

    #[DataProvider('queries')]
    public function test_filters_selects_and_events_are_preserved(string $class, string $kind): void
    {
        DB::table('posts')->insert([
            ['title' => 'visible', 'published' => true],
            ['title' => 'hidden', 'published' => false],
        ]);
        $query = $class::make()->addFilter(new PublishedFilter)->withSelect(['posts.id', 'posts.title']);
        $model = $kind === 'Sole' ? $query->effect() : $query->effect()->items()[0];
        $this->assertSame('visible', $model->title);
        $this->assertSame(['id', 'title'], array_keys($model->getAttributes()));
        Event::fake([ResultExecuted::class]);

        if ($kind === 'Sole') {
            DB::table('posts')->where('published', false)->delete();
        }

        $class::result();
        $class::result();
        Event::assertDispatchedTimes(ResultExecuted::class, 2);
    }

    #[DataProvider('cachedQueries')]
    public function test_cache_hits_and_tag_and_base_invalidation(string $class, string $kind): void
    {
        DB::table('posts')->insert(['title' => 'original']);
        $query = $class::make()->setCacheTags('additional-results');
        DB::enableQueryLog();
        $this->assertSame('original', $this->title($query, $kind));
        $this->assertSame('original', $this->title($class::make()->setCacheTags('additional-results'), $kind));
        $this->assertCount(1, DB::getQueryLog());
        DB::table('posts')->update(['title' => 'changed']);
        DB::flushQueryLog();
        $this->assertSame('original', $this->title($query, $kind));
        Cache::store('array')->tags('additional-results')->flush();
        $this->assertSame('changed', $this->title($query, $kind));
        $class::flushCache();
        $this->assertSame('changed', $this->title($query, $kind));
        $this->assertCount(2, DB::getQueryLog());
    }

    #[DataProvider('cachedQueries')]
    public function test_without_cache_and_non_positive_ttls_preserve_old_entries(string $class, string $kind): void
    {
        DB::table('posts')->insert(['title' => 'original']);
        $query = $class::make();
        $this->title($query, $kind);
        DB::table('posts')->update(['title' => 'fresh']);
        DB::enableQueryLog();

        foreach (['withoutCache', 0, -1] as $bypass) {
            $query = $class::make();
            $bypass === 'withoutCache' ? $query->withoutCache() : $query->setCacheFor($bypass);
            $this->assertSame('fresh', $this->title($query, $kind));
            $this->assertSame('fresh', $this->title($query, $kind));
        }

        $this->assertCount(6, DB::getQueryLog());
        $this->assertSame('original', $this->title($class::make(), $kind));
        $this->assertCount(6, DB::getQueryLog());
    }

    #[DataProvider('cachedQueries')]
    public function test_scope_and_callback_locks_bypass_cache(string $class, string $kind): void
    {
        DB::table('posts')->insert(['title' => 'original']);
        $this->title($class::make(), $kind);
        DB::table('posts')->update(['title' => 'fresh']);
        DB::enableQueryLog();

        foreach (['scope', 'callback', 'write'] as $mode) {
            $query = $class::make()->addFilter(new CallbackFilter(fn (Builder $builder) => match ($mode) {
                'scope' => $builder->withGlobalScope('lock', fn (Builder $query) => $query->lockForUpdate()),
                'callback' => $builder->beforeQuery(fn (QueryBuilder $query) => $query->lockForUpdate()),
                'write' => $builder->useWritePdo(),
            }));
            $this->assertSame('fresh', $this->title($query, $kind));
            $this->assertSame('fresh', $this->title($query, $kind));
        }

        $this->assertCount(6, DB::getQueryLog());
        $this->assertSame('original', $this->title($class::make(), $kind));
    }

    private function title(object $query, string $kind): string
    {
        $result = $query->effect();

        return $kind === 'Sole' ? $result->title : $result->items()[0]->title;
    }
}
