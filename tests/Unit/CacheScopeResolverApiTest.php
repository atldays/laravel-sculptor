<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Contracts\CacheScopeKey;
use Atldays\Sculptor\Support\CacheScopeResolver;
use Atldays\Sculptor\Tests\Fixtures\Data\OwnerScopeData;
use Atldays\Sculptor\Tests\Fixtures\Enums\ScopeStatus;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainAuthor;
use Atldays\Sculptor\Tests\Fixtures\Queries\ScopeValueConsumer;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CacheScopeResolverApiTest extends TestCase
{
    // Captured from the static resolver before the object API refactor.
    public static function legacyTags(): array
    {
        return [
            'integer' => [42, 'sculptor:scope:f4c616a7c343697a82b5539c5403ed867e327c609616d3d0e8ce56799d6a5cce'],
            'string' => ['42', 'sculptor:scope:a98e7005f4e764f7df20bb8f028b8267115919c64b08ec3c4fc2828329262af2'],
            'empty' => ['', 'sculptor:scope:d10f1ec5abaa634df97382687fbd8b8e352afca6a0a7b8c43290b4a0a5a05e1a'],
            'binary' => ["binary\xff", 'sculptor:scope:7e6855f7b2aa58780ba48f2a70aa58dd06eb75af1e17b42d7fb4579ee4f66863'],
            'false' => [false, 'sculptor:scope:f14da54acca0558044c9a43acfb790519509ee89f3a56c5135f5246d5a8ec87f'],
            'null' => [null, 'sculptor:scope:c43a102e8d9f5ebc2fac7e50af2239bcf77521c228da9561290c6477730ff0f7'],
            'float' => [1.5, 'sculptor:scope:78095cf037a77d590cdad7dcf1a1d21856b00ace55fa97452b245c8cce9c4844'],
            'negative zero' => [-0.0, 'sculptor:scope:037c7bf44724d1da4a5a4a6202e256f614d65b190e6b93f610f66389a52a7e73'],
            'enum' => [ScopeStatus::Active, 'sculptor:scope:aa0f4f8c0f3872d2247e1fe238a8c05107db7d1e482c4f750655092f43f24e35'],
            'model' => [(new PlainAuthor(['id' => 42]))->setConnection('tenant_a'), 'sculptor:scope:d64911d0299f85a5c0397586cff66d46ded702c179eb8cfa4d57fd433519db8b'],
            'custom' => [new OwnerScopeData(42), 'sculptor:scope:50f8f0cb746e37520e08bcc73e526fdd6722dceaa10c77f8aa4b17bedac138cd'],
        ];
    }

    #[DataProvider('legacyTags')]
    public function test_tags_match_the_values_written_before_the_refactor(mixed $value, string $expected): void
    {
        $query = new ScopeValueConsumer($value);

        $this->assertSame($expected, CacheScopeResolver::make($query)->tag());
        $this->assertSame($expected, CacheScopeResolver::make($query::class)->tagForArguments(['value' => $value]));
    }

    public function test_constructor_and_factory_support_both_query_and_class_contexts(): void
    {
        $query = new ScopeValueConsumer(42);
        $expected = CacheScopeResolver::make($query)->tag();

        foreach ([new CacheScopeResolver($query), CacheScopeResolver::make($query)] as $resolver) {
            $this->assertSame($expected, $resolver->tag());
            $this->assertSame($expected, $resolver->tagForArguments(['value' => 42]));
        }

        foreach ([new CacheScopeResolver($query::class), CacheScopeResolver::make($query::class)] as $resolver) {
            $this->assertSame($expected, $resolver->tagForArguments([42]));
        }
    }

    public function test_tag_requires_a_query_instance(): void
    {
        $resolver = CacheScopeResolver::make(ScopeValueConsumer::class);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires a query instance');

        $resolver->tag();
    }

    public function test_explicit_class_name_spelling_keeps_its_existing_tag(): void
    {
        $resolver = CacheScopeResolver::make('\\' . ScopeValueConsumer::class);

        $this->assertSame('sculptor:scope:47b6c673d09ebc9cbb06fd9e7364d80862fdf70632487ebd6fb326e574cd1efc', $resolver->tagForArguments(['value' => 42]));
    }

    public function test_reused_resolver_reads_current_query_values_and_validates_them_again(): void
    {
        $query = new ScopeValueConsumer(42);
        $resolver = CacheScopeResolver::make($query);
        $first = $resolver->tag();
        $query->value = 57;
        $second = $resolver->tag();

        $this->assertNotSame($first, $second);
        $this->assertSame($second, $resolver->tagForArguments(['value' => 57]));
        $this->assertSame($first, $resolver->tagForArguments(['value' => 42]));
        $this->assertSame($second, $resolver->tag());
        $query->value = [];
        $this->expectException(InvalidArgumentException::class);

        $resolver->tag();
    }

    public function test_class_context_can_be_reused_without_retaining_arguments(): void
    {
        $resolver = CacheScopeResolver::make(ScopeValueConsumer::class);
        $first = $resolver->tagForArguments(['value' => 42]);
        $second = $resolver->tagForArguments(['value' => 57]);

        $this->assertNotSame($first, $second);
        $this->assertSame($first, $resolver->tagForArguments([42]));
        $this->expectException(InvalidArgumentException::class);

        $resolver->tagForArguments([]);
    }

    public function test_reused_resolver_reads_current_model_identity(): void
    {
        $model = (new PlainAuthor(['id' => 42]))->setConnection('tenant_a');
        $resolver = CacheScopeResolver::make(new ScopeValueConsumer($model));
        $first = $resolver->tag();
        $model->setAttribute('name', 'Changed');
        $this->assertSame($first, $resolver->tag());
        $model->setAttribute('id', 57);
        $second = $resolver->tag();
        $this->assertNotSame($first, $second);
        $model->setConnection('tenant_b');
        $third = $resolver->tag();
        $this->assertNotSame($second, $third);
        $this->assertSame($third, $resolver->tagForArguments(['value' => clone $model]));
    }

    public function test_custom_scope_keys_are_read_at_resolution_time(): void
    {
        $value = new class() implements CacheScopeKey
        {
            public int $calls = 0;

            public string $key = 'first';

            public function cacheScopeKey(): string
            {
                $this->calls++;

                return $this->key;
            }
        };
        $resolver = CacheScopeResolver::make(new ScopeValueConsumer($value));
        $this->assertSame(0, $value->calls);
        $first = $resolver->tag();
        $this->assertSame(1, $value->calls);
        $value->key = 'second';
        $this->assertNotSame($first, $resolver->tag());
        $this->assertSame(2, $value->calls);
    }
}
