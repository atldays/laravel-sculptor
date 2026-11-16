<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Attributes\{Aggregate, PluckKey, ResultColumn};
use Atldays\Sculptor\Concerns\{QueryResultAggregate, QueryResultPluck, QueryResultValue};
use Atldays\Sculptor\Enums\AggregateFunction;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Queries\{AggregateProjectionQuery, PluckProjectionQuery, ValueProjectionQuery};
use Atldays\Sculptor\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class ProjectionConfigurationTest extends TestCase
{
    public function test_attributes_are_inherited_and_can_be_overridden_on_child_classes_and_at_runtime(): void
    {
        $inherited = new class() extends ValueProjectionQuery {};
        $this->assertSame('posts.title', $inherited->resultColumn());
        $child = new #[ResultColumn('posts.published')] class extends ValueProjectionQuery {};
        $this->assertSame('posts.published', $child->resultColumn());
        $this->assertSame('posts.id', $child->withResultColumn('posts.id')->resultColumn());

        $pluck = new #[PluckKey(null)] class extends PluckProjectionQuery {};
        $this->assertNull($pluck->pluckKey());
        $this->assertSame('posts.id', $pluck->withPluckKey('posts.id')->pluckKey());
        $this->assertNull($pluck->withPluckKey(null)->pluckKey());

        $aggregate = new #[Aggregate('max')] class extends AggregateProjectionQuery {};
        $this->assertSame(AggregateFunction::Max, $aggregate->aggregateFunction());
        $this->assertSame(AggregateFunction::Avg, $aggregate->withAggregateFunction(AggregateFunction::Avg)->aggregateFunction());
        $this->assertSame(AggregateFunction::Min, $aggregate->withAggregateFunction('MIN')->aggregateFunction());
    }

    public function test_each_projection_requires_a_column(): void
    {
        $queries = [
            new class() extends Query
            {
                use QueryResultValue;
            },
            new class() extends Query
            {
                use QueryResultPluck;
            },
            new class() extends Query
            {
                use QueryResultAggregate;
            },
        ];

        foreach ($queries as $query) {
            try {
                $query->effect();
                $this->fail('Missing result column must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('result column is required', $exception->getMessage());
            }
        }
    }

    public function test_aggregate_requires_an_operation(): void
    {
        $query = new class() extends Query
        {
            use QueryResultAggregate;
        };
        $query->withResultColumn('posts.id');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('aggregate function is required');
        $query->effect();
    }

    public static function invalidSettings(): iterable
    {
        yield 'empty attribute column' => ['column attribute', ''];
        yield 'blank attribute column' => ['column attribute', '  '];
        yield 'empty runtime column' => ['column runtime', ''];
        yield 'blank runtime column' => ['column runtime', "\t"];
        yield 'empty attribute key' => ['key attribute', ''];
        yield 'blank runtime key' => ['key runtime', '  '];
        yield 'count has its own trait' => ['function attribute', 'count'];
        yield 'unknown function' => ['function runtime', 'median'];
        yield 'empty function' => ['function runtime', ''];
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_are_rejected(string $setting, string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        match ($setting) {
            'column attribute' => new ResultColumn($value),
            'column runtime' => (new ValueProjectionQuery)->withResultColumn($value),
            'key attribute' => new PluckKey($value),
            'key runtime' => (new PluckProjectionQuery)->withPluckKey($value),
            'function attribute' => new Aggregate($value),
            'function runtime' => (new AggregateProjectionQuery)->withAggregateFunction($value),
        };
    }

    public function test_result_traits_require_a_query_contract(): void
    {
        foreach ([
            new class()
            {
                use QueryResultValue;
            },
            new class()
            {
                use QueryResultPluck;
            },
            new class()
            {
                use QueryResultAggregate;
            },
        ] as $query) {
            try {
                $query->effect();
                $this->fail('The query contract must be required.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('WithQuery', $exception->getMessage());
            }
        }
    }
}
