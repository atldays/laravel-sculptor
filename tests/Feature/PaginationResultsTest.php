<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Tests\Fixtures\Filters\CallbackFilter;
use Atldays\Sculptor\Tests\Fixtures\Queries;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\{CursorPaginator, Paginator};
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class PaginationResultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Paginator::currentPageResolver(fn () => 1);
        CursorPaginator::currentCursorResolver(fn () => null);
        Paginator::currentPathResolver(fn () => 'https://example.test/posts');

        foreach (range(1, 5) as $id) {
            DB::table('posts')->insert(['title' => 'post ' . $id, 'published' => $id % 2 === 1]);
        }
    }

    public static function queries(): iterable
    {
        foreach (AdditionalResultsTest::queries() as $name => [$class, $kind]) {
            if ($kind !== 'Sole') {
                yield $name => [$class, $kind];
            }
        }

        yield 'plain length-aware' => [Queries\PaginatedPostsQuery::class, 'Paginated'];
        yield 'cached length-aware' => [Queries\CachedPaginatedPostsQuery::class, 'Paginated'];
    }

    public static function cursorQueries(): iterable
    {
        foreach (self::queries() as $name => [$class, $kind]) {
            if ($kind === 'CursorPaginated') {
                yield $name => [$class];
            }
        }
    }

    public static function numberedQueries(): iterable
    {
        foreach (self::queries() as $name => [$class, $kind]) {
            if ($kind !== 'CursorPaginated') {
                yield $name => [$class, $kind];
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

    #[DataProvider('queries')]
    public function test_native_page_sizes_navigation_and_sql(string $class, string $kind): void
    {
        $query = $class::make()->perPage(2);
        DB::enableQueryLog();
        $first = $query->effect();
        $this->assertSame([1, 2], $this->ids($first));
        $this->assertNull($first->previousPageUrl());
        $this->assertNotNull($first->nextPageUrl());
        $this->assertCount($kind === 'Paginated' ? 2 : 1, DB::getQueryLog());

        if ($kind !== 'Paginated') {
            $this->assertStringNotContainsString('count(', strtolower(DB::getQueryLog()[0]['query']));
        }

        $second = $kind === 'CursorPaginated'
            ? $query->cursor($first->nextCursor())->effect()
            : $query->page(2)->effect();
        $this->assertSame([3, 4], $this->ids($second));
        $this->assertNotNull($second->previousPageUrl());
        $last = $kind === 'CursorPaginated'
            ? $query->cursor($second->nextCursor())->effect()
            : $query->page(3)->effect();
        $this->assertSame([5], $this->ids($last));
        $this->assertNull($last->nextPageUrl());
        $back = $kind === 'CursorPaginated'
            ? $query->cursor($last->previousCursor())->effect()
            : $query->page(2)->effect();
        $this->assertSame([3, 4], $this->ids($back));
        $this->assertSame($second->nextPageUrl(), $back->nextPageUrl());

        if ($kind === 'CursorPaginated') {
            foreach (DB::getQueryLog() as $sql) {
                $this->assertStringNotContainsString('offset', strtolower($sql['query']));
            }
        }
    }

    #[DataProvider('queries')]
    public function test_model_default_page_size_and_explicit_pagination_columns(string $class, string $kind): void
    {
        $query = $class::make();
        $this->assertSame($query->newModel()->getPerPage(), $query->effect()->perPage());
        $page = $query->perPage(1)->paginationColumns(['posts.id', 'posts.title'])->effect();
        $this->assertSame(['id', 'title'], array_keys($page->items()[0]->getAttributes()));
        $this->assertSame([1], $this->ids($page));
        $this->assertSame(['posts.*'], $query->select());
    }

    #[DataProvider('numberedQueries')]
    public function test_request_pages_resolve_once_and_do_not_mutate_configuration(string $class, string $kind): void
    {
        $page = 1;
        $calls = 0;
        Paginator::currentPageResolver(function ($name) use (&$page, &$calls) {
            $calls++;
            $this->assertSame('posts_page', $name);

            return $page;
        });
        $query = $class::make()->perPage(2)->pageName('posts_page');
        $this->assertSame([1, 2], $this->ids($query->effect()));
        $page = 2;
        $second = $query->effect();
        $this->assertSame([3, 4], $this->ids($second));
        $this->assertStringContainsString('posts_page=3', $second->nextPageUrl());
        $page = 1;
        $this->assertSame([1, 2], $this->ids($query->effect()));
        $this->assertSame(3, $calls);
        $this->assertNull($query->paginationData()->page);
        $this->assertSame([5], $this->ids($query->page(3)->effect()));
        $this->assertSame(3, $calls);
    }

    #[DataProvider('cursorQueries')]
    public function test_request_cursors_resolve_once_with_explicit_and_first_page_overrides(string $class): void
    {
        $cursor = null;
        $calls = 0;
        CursorPaginator::currentCursorResolver(function ($name) use (&$cursor, &$calls) {
            $calls++;
            $this->assertSame('posts_cursor', $name);

            return $cursor;
        });
        $query = $class::make()->perPage(2)->cursorName('posts_cursor');
        $first = $query->effect();
        $this->assertSame([1, 2], $this->ids($first));
        $cursor = $first->nextCursor();
        $second = $query->effect();
        $this->assertSame([3, 4], $this->ids($second));
        $this->assertStringContainsString('posts_cursor=', $second->nextPageUrl());
        $this->assertNull($query->cursorPaginationData()->cursor);
        $this->assertSame([3, 4], $this->ids($query->cursor($cursor->encode())->effect()));
        $this->assertSame([1, 2], $this->ids($query->cursor('')->effect()));
        $this->assertSame([1, 2], $this->ids($query->cursor('invalid')->effect()));
        $this->assertSame(2, $calls);
        $this->assertSame([3, 4], $this->ids($query->cursor(null)->effect()));
        $this->assertSame(3, $calls);
    }

    #[DataProvider('cursorQueries')]
    public function test_composite_descending_order_and_backwards_cursors_match_laravel(string $class): void
    {
        $query = $class::make()->perPage(2)->addFilter(new CallbackFilter(fn (Builder $query) => $query
            ->orderByDesc('published')->orderByDesc('id')));
        $first = $query->effect();
        $this->assertSame([5, 3], $this->ids($first));
        $second = $query->cursor($first->nextCursor())->effect();
        $this->assertSame([1, 4], $this->ids($second));
        $back = $query->cursor($second->previousCursor())->effect();
        $this->assertSame([5, 3], $this->ids($back));
        $this->assertSame($first->nextCursor()->encode(), $back->nextCursor()->encode());
    }

    #[DataProvider('cachedQueries')]
    public function test_pages_sizes_columns_and_link_options_are_isolated(string $class, string $kind): void
    {
        $query = $class::make()->perPage(2);
        $first = $query->effect();
        $first->appends(['poison' => 'yes'])->fragment('changed')->withPath('https://wrong.test');
        DB::enableQueryLog();
        $again = $query->effect();
        $this->assertCount(0, DB::getQueryLog());
        $this->assertStringStartsWith('https://example.test/posts?', $again->nextPageUrl());
        $this->assertStringNotContainsString('poison', $again->nextPageUrl());
        $this->assertStringNotContainsString('#', $again->nextPageUrl());
        $this->assertSame([1], $this->ids($query->perPage(1)->effect()));
        $projected = $query->paginationColumns('posts.id')->effect();
        $this->assertSame(['id'], array_keys($projected->items()[0]->getAttributes()));
        $query->paginationColumns('*')->perPage(2);
        Paginator::currentPathResolver(fn () => 'https://other.test/posts');
        $this->assertStringStartsWith('https://other.test/posts?', $query->effect()->nextPageUrl());
        $this->assertSame([1, 2], $this->ids($query->effect()));
    }

    #[DataProvider('cachedQueries')]
    public function test_empty_pages_are_cached_and_base_invalidation_refreshes_them(string $class, string $kind): void
    {
        DB::table('posts')->delete();
        $query = $class::make()->perPage(2);
        DB::enableQueryLog();
        $this->assertSame([], $query->effect()->items());
        $this->assertSame([], $query->effect()->items());
        $this->assertCount(1, DB::getQueryLog());
        DB::table('posts')->insert(['title' => 'later']);
        $this->assertSame([], $query->effect()->items());
        $class::flushBaseCache();
        $this->assertCount(1, $query->effect()->items());
    }

    public function test_cursor_object_and_encoded_string_share_a_result_cache_entry(): void
    {
        $query = Queries\CachedCursorPaginatedPostsQuery::make()->perPage(2);
        $cursor = $query->effect()->nextCursor();
        DB::enableQueryLog();
        $this->assertSame([3, 4], $this->ids($query->cursor($cursor)->effect()));
        $this->assertSame([3, 4], $this->ids($query->cursor($cursor->encode())->effect()));
        $this->assertCount(1, DB::getQueryLog());
    }

    private function ids(object $paginator): array
    {
        return array_map(fn ($model) => $model->id, $paginator->items());
    }
}
