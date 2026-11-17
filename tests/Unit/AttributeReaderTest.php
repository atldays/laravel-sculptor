<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Attributes\Select;
use Atldays\Sculptor\Support\AttributeReader;
use Atldays\Sculptor\Tests\Fixtures\Queries\{AttributePostsQuery, InheritedAttributePostsQuery, PostsQuery};
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

    public function test_constructor_accepts_objects_and_class_names(): void
    {
        foreach ([new AttributePostsQuery, AttributePostsQuery::class] as $subject) {
            $reader = new AttributeReader($subject);
            $attribute = $reader->get(Select::class);

            $this->assertInstanceOf(Select::class, $attribute);
            $this->assertSame(['posts.id', 'posts.title', 'posts.author_id'], $attribute->columns());
        }
    }

    public function test_factory_retains_the_requested_reader_class(): void
    {
        $reader = AttributeReader::make(PostsQuery::class);

        $this->assertSame(AttributeReader::class, $reader::class);
        $this->assertInstanceOf(AttributeReader::class, $reader);
    }
}
