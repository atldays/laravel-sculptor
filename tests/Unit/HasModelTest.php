<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Tests\Fixtures\Models\Post;
use Atldays\Sculptor\Tests\Fixtures\Queries\MissingModelConsumer;
use Atldays\Sculptor\Tests\Fixtures\Queries\ModelConsumer;
use Atldays\Sculptor\Tests\TestCase;
use Webmozart\Assert\InvalidArgumentException;

class HasModelTest extends TestCase
{
    public function test_it_returns_model_class_and_instance(): void
    {
        $consumer = new ModelConsumer;

        $this->assertSame(Post::class, $consumer->model());
        $this->assertInstanceOf(Post::class, $consumer->newModel());
    }

    public function test_it_requires_model_property(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected the property');

        (new MissingModelConsumer)->model();
    }
}
