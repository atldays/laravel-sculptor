<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Attributes\CacheScope;
use Atldays\Sculptor\Contracts\CacheScopeKey;
use Atldays\Sculptor\Support\CacheScopeResolver;
use Atldays\Sculptor\Tests\Fixtures\Data\{FailingOwnerScopeData, OwnerScopeData};
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class CacheScopePartialInvalidationTest extends TestCase
{
    public function test_all_nonempty_subsets_have_tags_and_the_full_tag_is_preserved(): void
    {
        $query = $this->query();
        $reader = CacheScopeResolver::make($query);
        $resolver = CacheScopeResolver::make($query::class);
        $tags = $reader->tags();

        $this->assertCount(7, $tags);
        $this->assertCount(7, array_unique($tags));
        $this->assertSame($reader->tag(), $tags[0]);

        foreach ([
            ['userId' => 42],
            ['status' => 'active'],
            ['tenantId' => 7],
            ['userId' => 42, 'status' => 'active'],
            ['userId' => 42, 'tenantId' => 7],
            ['status' => 'active', 'tenantId' => 7],
            ['userId' => 42, 'status' => 'active', 'tenantId' => 7],
        ] as $arguments) {
            $this->assertContains($resolver->tagForInvalidation($arguments), $tags);
        }

        $this->assertSame($tags[0], $resolver->tagForArguments(['userId' => 42]));
    }

    public function test_omitted_parameters_are_wildcards_including_defaults_and_required_parameters(): void
    {
        $query = $this->query();
        $resolver = CacheScopeResolver::make($query::class);
        $tag = $resolver->tagForInvalidation(['userId' => 42]);

        foreach (['active', 'archived', null] as $status) {
            $this->assertContains($tag, CacheScopeResolver::make($this->query(status: $status, tenantId: 9))->tags());
        }

        $this->assertNotContains($tag, CacheScopeResolver::make($this->query(userId: 57))->tags());
        $this->assertNotSame($tag, $resolver->tagForArguments(['userId' => 42]));
        $this->assertContains($resolver->tagForInvalidation(['status' => 'active']), CacheScopeResolver::make($this->query(userId: 57))->tags());
    }

    public function test_explicit_null_is_a_filter_and_argument_order_is_canonical(): void
    {
        $query = $this->query(status: null);
        $resolver = CacheScopeResolver::make($query::class);
        $tag = $resolver->tagForInvalidation(['userId' => 42, 'status' => null]);

        $this->assertContains($tag, CacheScopeResolver::make($query)->tags());
        $this->assertNotContains($tag, CacheScopeResolver::make($this->query())->tags());
        $this->assertSame($tag, $resolver->tagForInvalidation(['status' => null, 'userId' => 42]));
        $this->assertNotSame($tag, $resolver->tagForInvalidation(['userId' => 42]));
    }

    public function test_positional_arguments_follow_marked_order_and_skip_unmarked_dependencies(): void
    {
        $query = $this->query();
        $resolver = CacheScopeResolver::make($query::class);

        $this->assertSame($resolver->tagForInvalidation(['userId' => 42]), $resolver->tagForInvalidation([42]));
        $this->assertSame($resolver->tagForInvalidation(['userId' => 42, 'status' => null]), $resolver->tagForInvalidation([42, null]));
        $this->assertSame(CacheScopeResolver::make($query)->tag(), $resolver->tagForInvalidation([42, 'active', 7]));
    }

    public function test_empty_filters_are_rejected_even_when_all_constructor_parameters_have_defaults(): void
    {
        $query = new class()
        {
            public function __construct(
                #[CacheScope] private readonly int $userId = 42,
                #[CacheScope] private readonly ?string $status = null,
            ) {}
        };
        $resolver = CacheScopeResolver::make($query::class);
        $this->assertSame(CacheScopeResolver::make($query)->tag(), $resolver->tagForArguments([]));
        $this->expectException(InvalidArgumentException::class);

        $resolver->tagForInvalidation([]);
    }

    public function test_omitted_custom_defaults_are_not_normalized_for_partial_invalidation(): void
    {
        $query = new class(42, new OwnerScopeData(42))
        {
            public function __construct(
                #[CacheScope] private readonly int $userId,
                #[CacheScope] private readonly CacheScopeKey $context = new FailingOwnerScopeData(42),
            ) {}
        };
        $resolver = CacheScopeResolver::make($query::class);
        $this->assertContains($resolver->tagForInvalidation(['userId' => 42]), CacheScopeResolver::make($query)->tags());
        $this->expectException(RuntimeException::class);

        $resolver->tagForArguments(['userId' => 42]);
    }

    public static function invalidArguments(): array
    {
        return [
            'empty' => [[]],
            'unknown' => [['unknown' => 42]],
            'unmarked' => [['service' => new stdClass]],
            'duplicate' => [[42, 'userId' => 57]],
            'wrong user type' => [['userId' => '42']],
            'wrong status type' => [['status' => 42]],
            'non-nullable' => [['tenantId' => null]],
            'too many positional arguments' => [[42, 'active', 7, 'extra']],
        ];
    }

    #[DataProvider('invalidArguments')]
    public function test_invalid_partial_arguments_fail(array $arguments): void
    {
        $resolver = CacheScopeResolver::make($this->query()::class);
        $this->expectException(InvalidArgumentException::class);

        $resolver->tagForInvalidation($arguments);
    }

    public function test_class_only_context_cannot_enumerate_entry_tags(): void
    {
        $resolver = CacheScopeResolver::make($this->query()::class);
        $this->expectException(LogicException::class);

        $resolver->tags();
    }

    public function test_unmarked_queries_have_no_scope_tags_and_cannot_flush_a_partial_scope(): void
    {
        $resolver = CacheScopeResolver::make(new stdClass);
        $this->assertSame([], $resolver->tags());
        $this->expectException(LogicException::class);

        $resolver->tagForInvalidation(['userId' => 42]);
    }

    public function test_reusing_a_resolver_reads_values_and_normalizes_each_custom_component_once(): void
    {
        $component = new class() implements CacheScopeKey
        {
            public int $calls = 0;

            public string $key = 'first';

            public function cacheScopeKey(): string
            {
                $this->calls++;

                return $this->key;
            }
        };
        $query = new class($component)
        {
            public function __construct(
                #[CacheScope] public readonly CacheScopeKey $context,
                #[CacheScope] public string $status = 'active',
            ) {}
        };
        $resolver = CacheScopeResolver::make($query);
        $this->assertSame(0, $component->calls);
        $first = $resolver->tags();
        $this->assertCount(3, $first);
        $this->assertSame(1, $component->calls);
        $component->key = 'second';
        $query->status = 'archived';
        $second = $resolver->tags();
        $this->assertSame(2, $component->calls);
        $this->assertSame([], array_intersect($first, $second));
    }

    private function query(int $userId = 42, ?string $status = 'active', int $tenantId = 7): object
    {
        return new class($userId, new stdClass, $status, $tenantId)
        {
            public function __construct(
                #[CacheScope] public readonly int $userId,
                private readonly object $service,
                #[CacheScope] public readonly ?string $status = 'active',
                #[CacheScope] public readonly int $tenantId = 7,
            ) {}
        };
    }
}
