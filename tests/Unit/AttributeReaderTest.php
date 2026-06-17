<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Attributes\Helpers\AttributeReader;
use Atldays\Sculptor\Attributes\Select;
use Atldays\Sculptor\Tests\Fixtures\Queries\AttributePostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\InheritedAttributePostsQuery;
use Atldays\Sculptor\Tests\Fixtures\Queries\PostsQuery;
use Atldays\Sculptor\Tests\TestCase;

class AttributeReaderTest extends TestCase
{
    public function test_it_reads_attribute_from_object(): void
    {
        $attribute = AttributeReader::make(new AttributePostsQuery)->get(Select::class);

        $this->assertInstanceOf(Select::class, $attribute);
        $this->assertSame(['posts.id', 'posts.title', 'posts.author_id'], $attribute->columns());
    }

    public function test_it_reads_attribute_from_class_string(): void
    {
        $attribute = AttributeReader::make(AttributePostsQuery::class)->get(Select::class);

        $this->assertInstanceOf(Select::class, $attribute);
        $this->assertSame(['posts.id', 'posts.title', 'posts.author_id'], $attribute->columns());
    }

    public function test_it_reads_attribute_from_parent_class(): void
    {
        $attribute = AttributeReader::make(new InheritedAttributePostsQuery)->get(Select::class);

        $this->assertInstanceOf(Select::class, $attribute);
        $this->assertSame(['posts.id', 'posts.title', 'posts.author_id'], $attribute->columns());
    }

    public function test_it_returns_null_when_attribute_is_missing(): void
    {
        $this->assertNull(AttributeReader::make(new PostsQuery)->get(Select::class));
    }
}
