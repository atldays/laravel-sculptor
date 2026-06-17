<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Tests\Fixtures\Filters\PublishedFilter;
use Atldays\Sculptor\Tests\Fixtures\Models\Author;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;
use Atldays\Sculptor\Tests\Fixtures\Queries\ArraySelectPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\AttributePostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\AttributePropertyDefaultsPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\AttributeStringSelectPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\BasePostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\FilteredPaginatedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\FirstPostQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\InheritedAttributePostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\PaginatedPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\PostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\StringSelectPostsQuery;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Pagination\Paginator;
use Webmozart\Assert\InvalidArgumentException;

class QueryTest extends TestCase
{
    public function test_select_defaults_to_all_model_columns(): void
    {
        $query = new PostsQuery;

        $this->assertSame(['posts.*'], $query->select());
    }

    public function test_select_uses_scalar_property_when_defined(): void
    {
        $query = new StringSelectPostsQuery;

        $this->assertSame(['posts.title'], $query->select());
    }

    public function test_select_uses_array_property_when_defined(): void
    {
        $query = new ArraySelectPostsQuery;

        $this->assertSame(['posts.id', 'posts.title'], $query->select());
    }

    public function test_select_uses_scalar_attribute_when_defined(): void
    {
        $query = new AttributeStringSelectPostsQuery;

        $this->assertSame(['posts.title'], $query->select());
    }

    public function test_select_uses_array_attribute_when_defined(): void
    {
        $query = new AttributePostsQuery;

        $this->assertSame(['posts.id', 'posts.title', 'posts.author_id'], $query->select());
    }

    public function test_select_property_overrides_attribute(): void
    {
        $query = new AttributePropertyDefaultsPostsQuery;

        $this->assertSame(['posts.title'], $query->select());
    }

    public function test_with_select_overrides_defined_columns(): void
    {
        $query = (new StringSelectPostsQuery)->withSelect(['posts.id']);

        $this->assertSame(['posts.id'], $query->select());
    }

    public function test_with_select_overrides_attribute_columns(): void
    {
        $query = (new AttributePostsQuery)->withSelect(['posts.id']);

        $this->assertSame(['posts.id'], $query->select());
    }

    public function test_relations_default_to_with_property_and_can_be_reset(): void
    {
        $query = new PostsQuery;

        $this->assertSame(['author'], $query->relations()->values()->all());

        $query->withRelations('customRelation');
        $this->assertSame(['customRelation'], $query->relations()->values()->all());

        $query->withoutRelations();
        $this->assertTrue($query->relations()->isEmpty());

        $query->resetRelations();
        $this->assertSame(['author'], $query->relations()->values()->all());
    }

    public function test_relations_default_to_attribute_and_can_be_reset(): void
    {
        $query = new AttributePostsQuery;

        $this->assertSame(['author'], $query->relations()->values()->all());

        $query->withRelations('customRelation');
        $this->assertSame(['customRelation'], $query->relations()->values()->all());

        $query->withoutRelations();
        $this->assertTrue($query->relations()->isEmpty());

        $query->resetRelations();
        $this->assertSame(['author'], $query->relations()->values()->all());
    }

    public function test_with_property_overrides_attribute_relations(): void
    {
        $query = new AttributePropertyDefaultsPostsQuery;

        $this->assertSame(['author'], $query->relations()->values()->all());
    }

    public function test_query_applies_filters_relations_and_limit(): void
    {
        $author = Author::create(['name' => 'Jane']);

        Post::create(['author_id' => $author->id, 'title' => 'Visible', 'published' => true]);
        Post::create(['author_id' => $author->id, 'title' => 'Hidden', 'published' => false]);

        $result = PostsQuery::make()
            ->addFilter(new PublishedFilter)
            ->limit(1)
            ->effect();

        $this->assertCount(1, $result);
        $this->assertSame('Visible', $result->sole()->title);
        $this->assertTrue($result->sole()->relationLoaded('author'));
    }

    public function test_query_applies_attribute_defaults(): void
    {
        $author = Author::create(['name' => 'Jane']);

        foreach (range(1, 12) as $index) {
            Post::create(['author_id' => $author->id, 'title' => "Post {$index}", 'published' => true]);
        }

        $result = AttributePostsQuery::make()->effect();

        $this->assertCount(10, $result);
        $this->assertSame(['id', 'title', 'author_id'], array_keys($result->first()->getAttributes()));
        $this->assertTrue($result->every(fn (Post $post) => $post->relationLoaded('author')));
    }

    public function test_limit_method_overrides_attribute_limit(): void
    {
        foreach (range(1, 12) as $index) {
            Post::create(['title' => "Post {$index}", 'published' => true]);
        }

        $result = AttributePostsQuery::make()
            ->limit(3)
            ->effect();

        $this->assertCount(3, $result);
    }

    public function test_without_limit_disables_attribute_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit is required to fetch collection');

        AttributePostsQuery::make()
            ->withoutLimit()
            ->effect();
    }

    public function test_query_attributes_are_inherited_from_parent_class(): void
    {
        $query = new InheritedAttributePostsQuery;

        $this->assertSame(Post::class, $query->model());
        $this->assertSame(['posts.id', 'posts.title', 'posts.author_id'], $query->select());
        $this->assertSame(['author'], $query->relations()->values()->all());
        $this->assertTrue($query->hasLimit());
    }

    public function test_limit_flags_can_be_toggled(): void
    {
        $query = new PostsQuery;

        $this->assertFalse($query->hasLimit());

        $query->limit(3);
        $this->assertTrue($query->hasLimit());

        $query->withoutLimit();
        $this->assertFalse($query->hasLimit());
    }

    public function test_collection_query_requires_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit is required to fetch collection');

        PostsQuery::make()->effect();
    }

    public function test_first_query_returns_first_model_or_null(): void
    {
        $this->assertNull(FirstPostQuery::result());

        $first = Post::create(['title' => 'First', 'published' => true]);
        Post::create(['title' => 'Second', 'published' => true]);

        $this->assertSame($first->id, FirstPostQuery::result()?->id);
    }

    public function test_base_query_can_be_used_without_filters_integration(): void
    {
        $author = Author::create(['name' => 'Jane']);

        Post::create(['author_id' => $author->id, 'title' => 'Visible', 'published' => true]);
        Post::create(['author_id' => $author->id, 'title' => 'Hidden', 'published' => false]);

        $result = BasePostsQuery::make()
            ->limit(10)
            ->effect();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn (Post $post) => $post->relationLoaded('author')));
    }

    public function test_paginated_query_returns_length_aware_paginator(): void
    {
        foreach (range(1, 25) as $index) {
            Post::create(['title' => "Post {$index}", 'published' => true]);
        }

        $result = PaginatedPostsQuery::make()
            ->perPage(10)
            ->page(2)
            ->effect();

        $this->assertSame(2, $result->currentPage());
        $this->assertSame(10, $result->perPage());
        $this->assertCount(10, $result->items());
        $this->assertSame(25, $result->total());
    }

    public function test_paginated_query_uses_model_per_page_when_not_overridden(): void
    {
        foreach (range(1, 25) as $index) {
            Post::create(['title' => "Post {$index}", 'published' => true]);
        }

        $result = PaginatedPostsQuery::make()->effect();

        $this->assertSame(1, $result->currentPage());
        $this->assertSame(7, $result->perPage());
        $this->assertCount(7, $result->items());
    }

    public function test_paginated_query_uses_laravel_current_page_resolver_when_page_is_not_overridden(): void
    {
        foreach (range(1, 25) as $index) {
            Post::create(['title' => "Post {$index}", 'published' => true]);
        }

        Paginator::currentPageResolver(fn (string $pageName = 'page') => $pageName === 'page' ? 2 : 1);

        try {
            $result = PaginatedPostsQuery::make()
                ->perPage(10)
                ->effect();
        } finally {
            Paginator::currentPageResolver(fn () => 1);
        }

        $this->assertSame(2, $result->currentPage());
        $this->assertSame(10, $result->perPage());
        $this->assertCount(10, $result->items());
    }

    public function test_paginated_query_accepts_constructor_arguments_with_runtime_pagination(): void
    {
        foreach (range(1, 12) as $index) {
            Post::create(['title' => "Post {$index}", 'published' => true]);
        }

        $result = FilteredPaginatedPostsQuery::make(minId: 6)
            ->perPage(5)
            ->page(1)
            ->effect();

        $this->assertSame(6, $result->items()[0]->id);
        $this->assertCount(5, $result->items());
        $this->assertSame(7, $result->total());
    }
}
