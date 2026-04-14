<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Tests\Fixtures\Filters\PublishedFilter;
use Atldays\Sculptor\Tests\Fixtures\Models\Author;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;
use Atldays\Sculptor\Tests\Fixtures\Queries\ArraySelectPostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\BasePostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\FirstPostQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\PostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\StringSelectPostsQuery;
use Atldays\Sculptor\Tests\TestCase;
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

    public function test_with_select_overrides_defined_columns(): void
    {
        $query = (new StringSelectPostsQuery)->withSelect(['posts.id']);

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
}
