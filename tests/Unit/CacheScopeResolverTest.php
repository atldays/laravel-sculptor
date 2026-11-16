<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Attributes\CacheScope;
use Atldays\Sculptor\Support\CacheScopeResolver;
use Atldays\Sculptor\Tests\Fixtures\Enums\ScopeStatus;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

class CacheScopeResolverTest extends TestCase
{
    public static function supportedValues(): array
    {
        return [[42], ['42'], [''], ['0'], [' '], ["binary\xff"], [false], [true], [null], [1.5], [ScopeStatus::Active]];
    }

    #[DataProvider('supportedValues')]
    public function test_instance_and_argument_paths_use_identical_stable_normalization(mixed $value): void
    {
        $query = $this->query($value);

        $this->assertSame(CacheScopeResolver::make($query)->tag(), CacheScopeResolver::make($query::class)->tagForArguments(['value' => $value]));
        $this->assertSame(CacheScopeResolver::make($query)->tag(), CacheScopeResolver::make($this->query($value))->tag());
    }

    public function test_types_and_delimiter_like_values_cannot_collide(): void
    {
        $values = [42, '42', false, 0, '0', null, '', ' ', 1, 1.0, ScopeStatus::Active, 'active'];
        $tags = array_map(fn (mixed $value) => CacheScopeResolver::make($this->query($value))->tag(), $values);
        $this->assertCount(count($values), array_unique($tags));
        $this->assertNotSame(
            CacheScopeResolver::make($this->composite('a:b', 'c'))->tag(),
            CacheScopeResolver::make($this->composite('a', 'b:c'))->tag(),
        );
    }

    public function test_named_order_positional_arguments_and_optional_defaults_match_the_constructor(): void
    {
        $query = $this->composite('tenant', 'owner');
        $expected = CacheScopeResolver::make($query)->tag();

        $this->assertSame($expected, CacheScopeResolver::make($query::class)->tagForArguments(['owner' => 'owner', 'tenant' => 'tenant']));
        $this->assertSame($expected, CacheScopeResolver::make($query::class)->tagForArguments(['tenant', 'owner']));
        $this->assertSame($expected, CacheScopeResolver::make($query::class)->tagForArguments(['tenant' => 'tenant', 'owner' => 'owner', 'state' => null]));
    }

    public function test_integer_arguments_widen_consistently_for_float_parameters(): void
    {
        $query = new class(42)
        {
            public function __construct(#[CacheScope] private readonly float $amount) {}
        };

        $this->assertSame(CacheScopeResolver::make($query)->tag(), CacheScopeResolver::make($query::class)->tagForArguments(['amount' => 42]));
        $this->assertSame(CacheScopeResolver::make($query)->tag(), CacheScopeResolver::make($query::class)->tagForArguments(['amount' => 42.0]));
    }

    public function test_positive_and_negative_float_zero_share_the_same_scope(): void
    {
        $query = $this->query(-0.0);
        $this->assertSame(CacheScopeResolver::make($query)->tag(), CacheScopeResolver::make($query::class)->tagForArguments(['value' => 0.0]));
    }

    public function test_enum_defaults_and_unit_enums_are_supported_without_constructing_the_query(): void
    {
        $query = new class()
        {
            public function __construct(#[CacheScope] private readonly ScopeStatus $status = ScopeStatus::Active) {}
        };
        $this->assertSame(CacheScopeResolver::make($query)->tag(), CacheScopeResolver::make($query::class)->tagForArguments([]));
        $value = ScopeUnitValue::Visible;
        $this->assertSame(CacheScopeResolver::make($this->query($value))->tag(), CacheScopeResolver::make($this->query($value)::class)->tagForArguments(['value' => $value]));

        $this->expectException(InvalidArgumentException::class);
        CacheScopeResolver::make($query::class)->tagForArguments(['status' => 'active']);
    }

    public static function invalidArguments(): array
    {
        return [
            'missing all' => [[]],
            'missing owner' => [['tenant' => 'tenant']],
            'unknown name' => [['tenant' => 'tenant', 'owner' => 'owner', 'unknown' => 42]],
            'unmarked argument' => [['tenant' => 'tenant', 'owner' => 'owner', 'service' => new stdClass]],
            'too many positional values' => [['tenant', 'owner', null, 'extra']],
            'duplicate name' => [['tenant', 'tenant' => 'other', 'owner' => 'owner']],
            'wrong type' => [['tenant' => 42, 'owner' => 'owner']],
            'non-nullable value' => [['tenant' => null, 'owner' => 'owner']],
        ];
    }

    #[DataProvider('invalidArguments')]
    public function test_missing_unknown_duplicate_and_wrongly_typed_scope_arguments_fail(array $arguments): void
    {
        $query = $this->composite('tenant', 'owner');
        $this->expectException(InvalidArgumentException::class);

        CacheScopeResolver::make($query::class)->tagForArguments($arguments);
    }

    public static function unsupportedValues(): array
    {
        return [[[]], [new stdClass], [str('42')], [INF], [-INF], [NAN]];
    }

    #[DataProvider('unsupportedValues')]
    public function test_unsupported_values_fail_on_both_reading_and_flushing(mixed $value): void
    {
        $query = $this->query($value);

        try {
            CacheScopeResolver::make($query)->tag();
            $this->fail('An unsupported scope value must be rejected.');
        } catch (InvalidArgumentException) {
            $this->expectException(InvalidArgumentException::class);
            CacheScopeResolver::make($query::class)->tagForArguments(['value' => $value]);
        }
    }

    public function test_unmarked_queries_keep_their_existing_tags_and_cannot_flush_an_implicit_scope(): void
    {
        $query = new stdClass;
        $this->assertNull(CacheScopeResolver::make($query)->tag());
        $this->expectException(LogicException::class);
        CacheScopeResolver::make($query::class)->tagForArguments([]);
    }

    public function test_non_promoted_marked_parameters_are_configuration_errors(): void
    {
        $query = new class(42)
        {
            public function __construct(#[CacheScope] int $ownerId) {}
        };
        $this->expectException(LogicException::class);
        CacheScopeResolver::make($query)->tag();
    }

    public function test_uninitialized_scope_properties_are_configuration_errors(): void
    {
        $class = $this->query(42)::class;
        $query = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $this->expectException(LogicException::class);
        CacheScopeResolver::make($query)->tag();
    }

    public function test_union_types_preserve_the_selected_scalar_type(): void
    {
        $query = new class(42)
        {
            public function __construct(#[CacheScope] private readonly int|string $value) {}
        };
        $this->assertSame(CacheScopeResolver::make($query)->tag(), CacheScopeResolver::make($query::class)->tagForArguments(['value' => 42]));
        $this->assertNotSame(CacheScopeResolver::make($query)->tag(), CacheScopeResolver::make($query::class)->tagForArguments(['value' => '42']));
    }

    private function query(mixed $value): object
    {
        return new class($value)
        {
            public function __construct(#[CacheScope] private readonly mixed $value) {}
        };
    }

    private function composite(string $tenant, string $owner): object
    {
        return new class($tenant, new stdClass, $owner)
        {
            public function __construct(
                #[CacheScope] private readonly string $tenant,
                private readonly object $service,
                #[CacheScope] private readonly string $owner,
                #[CacheScope] private readonly ?string $state = null,
            ) {}
        };
    }
}

enum ScopeUnitValue
{
    case Visible;
}
