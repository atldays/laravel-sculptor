<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Attributes\CacheScope;
use Atldays\Sculptor\Cache\CacheScopeResolver;
use Atldays\Sculptor\Contracts\CacheScopeKey;
use Atldays\Sculptor\Tests\Fixtures\Data\OwnerScopeData;
use Atldays\Sculptor\Tests\Fixtures\Models\{Author, PlainAuthor};
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class CacheScopeObjectValuesTest extends TestCase
{
    public function test_model_identity_ignores_non_key_attributes_relations_and_persistence_flags(): void
    {
        $first = new Author(['id' => 42, 'name' => 'Old']);
        $second = new Author(['id' => 42, 'name' => 'Updated']);
        $first->exists = true;
        $first->setRelation('posts', collect([new PlainAuthor]));
        $second->setRelation('posts', collect());

        $this->assertSame($this->scopeTag($first), $this->scopeTag($second));
        $query = $this->scopeQuery($first);
        $this->assertSame($this->scopeTag($first), CacheScopeResolver::make($query::class)->tagForArguments(['value' => $second]));
    }

    public function test_model_class_connection_and_primary_key_each_partition_the_scope(): void
    {
        $first = new Author(['id' => 42]);
        $tags = [
            $this->scopeTag($first),
            $this->scopeTag(new PlainAuthor(['id' => 42])),
            $this->scopeTag(new Author(['id' => 57])),
            $this->scopeTag((new Author(['id' => 42]))->setConnection('another')),
        ];

        $this->assertCount(4, array_unique($tags));
    }

    public function test_default_connection_resolution_matches_explicit_names_without_resolving_a_connection(): void
    {
        $previous = Model::getConnectionResolver();
        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $resolver->shouldReceive('getDefaultConnection')->twice()->andReturn('tenant_a', 'tenant_b');
        $resolver->shouldNotReceive('connection');
        Model::setConnectionResolver($resolver);

        try {
            $implicit = new Author(['id' => 42]);
            $first = $this->scopeTag($implicit);
            $this->assertSame($first, $this->scopeTag((new Author(['id' => 42]))->setConnection('tenant_a')));
            $second = $this->scopeTag($implicit);
            $this->assertNotSame($first, $second);
            $this->assertSame($second, $this->scopeTag((new Author(['id' => 42]))->setConnection('tenant_b')));
        } finally {
            $previous === null ? Model::unsetConnectionResolver() : Model::setConnectionResolver($previous);
        }
    }

    public function test_zero_and_string_primary_keys_with_custom_key_names_are_supported(): void
    {
        $this->assertNotSame($this->scopeTag(new Author(['id' => 0])), $this->scopeTag(new Author(['id' => 42])));
        $model = new class() extends Model
        {
            protected $primaryKey = 'uuid';

            protected $keyType = 'string';

            public $incrementing = false;

            protected $guarded = [];
        };
        $first = (clone $model)->setRawAttributes(['uuid' => '00000000-0000-4000-8000-000000000042']);
        $same = (clone $model)->setRawAttributes(['uuid' => '00000000-0000-4000-8000-000000000042', 'name' => 'Other']);
        $other = (clone $model)->setRawAttributes(['uuid' => '00000000-0000-4000-8000-000000000057']);
        $this->assertSame($this->scopeTag($first), $this->scopeTag($same));
        $this->assertNotSame($this->scopeTag($first), $this->scopeTag($other));
    }

    public static function missingKeys(): array
    {
        return [['new'], ['partial'], ['array']];
    }

    #[DataProvider('missingKeys')]
    public function test_missing_or_unsupported_primary_keys_fail_on_both_paths(string $kind): void
    {
        $model = new Author(['name' => 'No key']);

        if ($kind === 'partial') {
            $model->exists = true;
        } elseif ($kind === 'array') {
            $model = new class() extends Model
            {
                public function getKey(): array
                {
                    return ['first' => 42, 'second' => 57];
                }
            };
        }

        $query = $this->scopeQuery($model);

        try {
            CacheScopeResolver::make($query)->tag();
            $this->fail('A model scope needs a scalar primary key.');
        } catch (InvalidArgumentException) {
            $this->expectException(InvalidArgumentException::class);
            CacheScopeResolver::make($query::class)->tagForArguments(['value' => $model]);
        }
    }

    public function test_custom_keys_are_stable_and_have_a_distinct_namespace_from_scalar_and_model_keys(): void
    {
        $first = new OwnerScopeData(42);
        $same = new OwnerScopeData(42);
        $different = new OwnerScopeData(57);
        $otherTenant = new OwnerScopeData(42, 'another');
        $this->assertSame($this->scopeTag($first), $this->scopeTag($same));
        $tags = [$this->scopeTag($first), $this->scopeTag($different), $this->scopeTag($otherTenant), $this->scopeTag($first->cacheScopeKey()), $this->scopeTag(new Author(['id' => 42]))];
        $this->assertCount(5, array_unique($tags));
    }

    public function test_custom_interface_controls_identity_across_different_dto_classes(): void
    {
        $first = new OwnerScopeData(42);
        $second = new class() implements CacheScopeKey
        {
            public function cacheScopeKey(): string
            {
                return json_encode(['default', 42], JSON_THROW_ON_ERROR);
            }
        };
        $query = new class($first)
        {
            public function __construct(#[CacheScope] private readonly CacheScopeKey $value) {}
        };

        $this->assertSame(CacheScopeResolver::make($query)->tag(), CacheScopeResolver::make($query::class)->tagForArguments(['value' => $second]));
    }

    public function test_explicit_custom_identity_takes_priority_on_a_model(): void
    {
        $model = new class() extends Model implements CacheScopeKey
        {
            public function cacheScopeKey(): string
            {
                return 'explicit-owner-scope';
            }
        };
        $changed = (clone $model)->setConnection('another')->setRawAttributes(['id' => 57]);
        $this->assertSame($this->scopeTag($model), $this->scopeTag($changed));
    }

    public function test_custom_key_is_evaluated_once_per_normalization_and_exceptions_propagate(): void
    {
        $value = new class() implements CacheScopeKey
        {
            public int $calls = 0;

            public function cacheScopeKey(): string
            {
                $this->calls++;

                return 'stable';
            }
        };
        $this->scopeTag($value);
        $this->assertSame(1, $value->calls);

        $failing = new class() implements CacheScopeKey
        {
            public function cacheScopeKey(): string
            {
                throw new RuntimeException('Cannot identify scope');
            }
        };
        $query = $this->scopeQuery($failing);
        $this->expectException(RuntimeException::class);
        CacheScopeResolver::make($query::class)->tagForArguments(['value' => $failing]);
    }

    public function test_models_and_custom_keys_compose_with_strictly_typed_constructor_parameters(): void
    {
        $model = new Author(['id' => 42]);
        $data = new OwnerScopeData(42);
        $query = new class($model, $data)
        {
            public function __construct(
                #[CacheScope] private readonly Model $owner,
                #[CacheScope] private readonly CacheScopeKey $context,
            ) {}
        };
        $expected = CacheScopeResolver::make($query)->tag();
        $this->assertSame($expected, CacheScopeResolver::make($query::class)->tagForArguments(['context' => new OwnerScopeData(42), 'owner' => clone $model]));
        $this->assertNotSame($expected, CacheScopeResolver::make($query::class)->tagForArguments(['context' => new OwnerScopeData(57), 'owner' => $model]));

        $this->expectException(InvalidArgumentException::class);
        CacheScopeResolver::make($query::class)->tagForArguments(['owner' => 42, 'context' => $data]);
    }

    public function test_normalizing_models_and_custom_keys_executes_no_sql(): void
    {
        $model = new Author(['id' => 42]);
        DB::enableQueryLog();
        $this->assertSame($this->scopeTag($model), $this->scopeTag(clone $model));
        $this->assertSame($this->scopeTag(new OwnerScopeData(42)), $this->scopeTag(new OwnerScopeData(42)));
        $this->assertCount(0, DB::getQueryLog());
    }

    private function scopeQuery(mixed $value): object
    {
        return new class($value)
        {
            public function __construct(#[CacheScope] private readonly mixed $value) {}
        };
    }

    private function scopeTag(mixed $value): ?string
    {
        return CacheScopeResolver::make($this->scopeQuery($value))->tag();
    }
}
