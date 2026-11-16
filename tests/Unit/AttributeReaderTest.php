<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Attributes\Helpers\AttributeReader as LegacyAttributeReader;
use Atldays\Sculptor\Attributes\Select;
use Atldays\Sculptor\Support\AttributeReader;
use Atldays\Sculptor\Tests\Fixtures\Queries\{AttributePostsQuery, InheritedAttributePostsQuery, PostsQuery};
use Atldays\Sculptor\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AttributeReaderTest extends TestCase
{
    public static function readerClasses(): array
    {
        return [
            'support' => [AttributeReader::class],
            'legacy' => [LegacyAttributeReader::class],
        ];
    }

    #[DataProvider('readerClasses')]
    public function test_it_reads_attribute_from_object(string $readerClass): void
    {
        $attribute = $readerClass::make(new AttributePostsQuery)->get(Select::class);

        $this->assertInstanceOf(Select::class, $attribute);
        $this->assertSame(['posts.id', 'posts.title', 'posts.author_id'], $attribute->columns());
    }

    #[DataProvider('readerClasses')]
    public function test_it_reads_attribute_from_class_string(string $readerClass): void
    {
        $attribute = $readerClass::make(AttributePostsQuery::class)->get(Select::class);

        $this->assertInstanceOf(Select::class, $attribute);
        $this->assertSame(['posts.id', 'posts.title', 'posts.author_id'], $attribute->columns());
    }

    #[DataProvider('readerClasses')]
    public function test_it_reads_attribute_from_parent_class(string $readerClass): void
    {
        $attribute = $readerClass::make(new InheritedAttributePostsQuery)->get(Select::class);

        $this->assertInstanceOf(Select::class, $attribute);
        $this->assertSame(['posts.id', 'posts.title', 'posts.author_id'], $attribute->columns());
    }

    #[DataProvider('readerClasses')]
    public function test_it_returns_null_when_attribute_is_missing(string $readerClass): void
    {
        $this->assertNull($readerClass::make(new PostsQuery)->get(Select::class));
    }

    #[DataProvider('readerClasses')]
    public function test_constructor_accepts_objects_and_class_names(string $readerClass): void
    {
        foreach ([new AttributePostsQuery, AttributePostsQuery::class] as $subject) {
            $reader = new $readerClass($subject);
            $attribute = $reader->get(Select::class);

            $this->assertInstanceOf(Select::class, $attribute);
            $this->assertSame(['posts.id', 'posts.title', 'posts.author_id'], $attribute->columns());
        }
    }

    #[DataProvider('readerClasses')]
    public function test_factory_retains_the_requested_reader_class(string $readerClass): void
    {
        $reader = $readerClass::make(PostsQuery::class);

        $this->assertSame($readerClass, $reader::class);
        $this->assertInstanceOf(AttributeReader::class, $reader);
    }
}
