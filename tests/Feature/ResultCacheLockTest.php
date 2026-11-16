<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultAll;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;
use Atldays\Sculptor\Tests\Fixtures\Queries\{CachedPaginatedPostsQuery, GenericCachedFirstPostQuery};
use Atldays\Sculptor\Tests\TestCase;
use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class ResultCacheLockTest extends TestCase
{
    public static function locks(): iterable
    {
        foreach (['update' => true, 'shared' => false, 'custom' => 'for update'] as $name => $lock) {
            foreach (['query', 'scope', 'callback'] as $stage) {
                yield $name . ' ' . $stage => [$lock, $stage];
            }
        }
    }

    #[DataProvider('locks')]
    public function test_locks_bypass_reads_and_writes_without_replacing_existing_cache(bool|string $lock, string $stage): void
    {
        DB::table('posts')->insert(['title' => 'Original', 'published' => true]);
        $unlocked = $this->makeQuery();
        $this->assertSame('Original', $unlocked->effect()->sole()->title);
        DB::table('posts')->update(['title' => 'Updated']);
        $locked = $this->lockedQuery($lock, $stage);
        DB::enableQueryLog();

        $this->assertSame('Updated', $locked->effect()->sole()->title);
        DB::table('posts')->update(['title' => 'Latest']);
        $this->assertSame('Latest', $locked->effect()->sole()->title);
        $this->assertSame('Original', $unlocked->effect()->sole()->title);
        $this->assertCount(3, DB::getQueryLog());
        $this->assertSame(2, $locked->queryBuilds);
    }

    #[DataProvider('locks')]
    public function test_locks_do_not_populate_an_empty_cache(bool|string $lock, string $stage): void
    {
        DB::table('posts')->insert(['title' => 'Original', 'published' => true]);
        $locked = $this->lockedQuery($lock, $stage);
        $this->assertSame('Original', $locked->effect()->sole()->title);
        DB::table('posts')->update(['title' => 'Updated']);

        $this->assertSame('Updated', $this->makeQuery()->effect()->sole()->title);
    }

    public function test_write_connection_queries_bypass_result_cache(): void
    {
        DB::table('posts')->insert(['title' => 'Original', 'published' => true]);
        $this->assertSame('Original', $this->makeQuery()->effect()->sole()->title);
        DB::table('posts')->update(['title' => 'Updated']);
        $query = $this->makeQuery(fn (Builder $query) => $query->useWritePdo());

        $this->assertSame('Updated', $query->effect()->sole()->title);
        $this->assertSame('Original', $this->makeQuery()->effect()->sole()->title);
    }

    public function test_key_and_execution_use_one_builder_with_scopes_and_callbacks_applied_once(): void
    {
        DB::table('posts')->insert([
            ['title' => 'Visible', 'published' => true],
            ['title' => 'Hidden', 'published' => false],
        ]);
        $scopeCalls = 0;
        $callbackCalls = 0;
        $query = $this->makeQuery(function (Builder $query) use (&$scopeCalls, &$callbackCalls) {
            $query->withGlobalScope('published', function (EloquentBuilder $query) use (&$scopeCalls) {
                $scopeCalls++;
                $query->where('published', true);
            });
            $query->beforeQuery(function (QueryBuilder $query) use (&$callbackCalls) {
                $callbackCalls++;
                $query->where('title', 'Visible');
            });
        });
        DB::enableQueryLog();

        $this->assertSame(['Visible'], $query->effect()->pluck('title')->all());
        $this->assertSame(1, $query->queryBuilds);
        $this->assertSame(1, $scopeCalls);
        $this->assertSame(1, $callbackCalls);

        $this->assertSame(['Visible'], $query->effect()->pluck('title')->all());
        $this->assertSame(2, $query->queryBuilds);
        $this->assertSame(2, $scopeCalls);
        $this->assertSame(2, $callbackCalls);
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame([true, 'Visible'], DB::getQueryLog()[0]['bindings']);
    }

    public function test_scoped_bindings_are_included_in_the_cache_key(): void
    {
        DB::table('posts')->insert([
            ['title' => 'Visible', 'published' => true],
            ['title' => 'Hidden', 'published' => false],
        ]);
        $title = 'Visible';
        $query = $this->makeQuery(function (Builder $query) use (&$title) {
            $query->withGlobalScope('title', fn (EloquentBuilder $query) => $query->where('title', $title));
        });

        $this->assertSame('Visible', $query->effect()->sole()->title);
        $title = 'Hidden';
        $this->assertSame('Hidden', $query->effect()->sole()->title);
    }

    public function test_existing_first_and_paginated_results_use_the_prepared_locked_builder(): void
    {
        DB::table('posts')->insert(['title' => 'Original', 'published' => true]);

        $queries = [
            new class() extends GenericCachedFirstPostQuery
            {
                public int $queryBuilds = 0;

                public function query(): Builder
                {
                    $this->queryBuilds++;

                    return parent::query()->beforeQuery(fn (QueryBuilder $query) => $query->lockForUpdate());
                }
            },
            new class() extends CachedPaginatedPostsQuery
            {
                public int $queryBuilds = 0;

                public function query(): Builder
                {
                    $this->queryBuilds++;

                    return parent::query()->beforeQuery(fn (QueryBuilder $query) => $query->lockForUpdate());
                }
            },
        ];

        foreach ($queries as $query) {
            DB::table('posts')->update(['title' => 'Original']);
            $first = $query->effect();
            DB::table('posts')->update(['title' => 'Updated']);
            $second = $query->effect();

            $this->assertSame('Original', (method_exists($first, 'items') ? $first->items()[0] : $first)->title);
            $this->assertSame('Updated', (method_exists($second, 'items') ? $second->items()[0] : $second)->title);
            $this->assertSame(2, $query->queryBuilds);
        }
    }

    private function lockedQuery(bool|string $lock, string $stage): CachedQuery
    {
        return $this->makeQuery(function (Builder $query) use ($lock, $stage) {
            match ($stage) {
                'query' => $query->lock($lock),
                'scope' => $query->withGlobalScope('lock', fn (EloquentBuilder $query) => $query->lock($lock)),
                'callback' => $query->beforeQuery(fn (QueryBuilder $query) => $query->lock($lock)),
            };
        });
    }

    private function makeQuery(?Closure $configure = null): CachedQuery
    {
        return new class($configure) extends CachedQuery
        {
            use QueryResultAll;

            protected string $model = PlainPost::class;

            public int $queryBuilds = 0;

            public function __construct(private readonly ?Closure $configure) {}

            public function query(): Builder
            {
                $this->queryBuilds++;
                $query = parent::query();

                if ($this->configure !== null) {
                    ($this->configure)($query);
                }

                return $query;
            }
        };
    }
}
