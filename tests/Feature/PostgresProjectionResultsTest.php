<?php

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Enums\AggregateFunction;
use Atldays\Sculptor\Tests\Fixtures\Filters\CallbackFilter;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};

#[Group('postgres')]
class PostgresProjectionResultsTest extends TestCase
{
    private bool $postgresConfigured = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!getenv('SCULPTOR_PGSQL_DATABASE')) {
            $this->markTestSkipped('Set SCULPTOR_PGSQL_DATABASE to run PostgreSQL projection tests.');
        }

        config()->set('database.connections.projection_pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('SCULPTOR_PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('SCULPTOR_PGSQL_PORT') ?: 5432,
            'database' => getenv('SCULPTOR_PGSQL_DATABASE'),
            'username' => getenv('SCULPTOR_PGSQL_USER') ?: 'postgres',
            'password' => getenv('SCULPTOR_PGSQL_PASSWORD') ?: '',
            'charset' => 'utf8',
            'prefix' => 'sculptor_' . bin2hex(random_bytes(6)) . '_',
        ]);
        DB::setDefaultConnection('projection_pgsql');
        $this->postgresConfigured = true;
        DB::connection()->getSchemaBuilder()->create('posts', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('title');
            $table->boolean('published');
            $table->integer('author_id')->nullable();
            $table->decimal('amount', 38, 8)->nullable();
        });
        DB::table('posts')->insert([
            ['id' => 1, 'title' => 'alpha', 'published' => false, 'amount' => '12345678901234567890.12345678'],
            ['id' => 2, 'title' => 'beta', 'published' => true, 'amount' => '0.00000002'],
        ]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->postgresConfigured) {
                DB::connection('projection_pgsql')->getSchemaBuilder()->dropIfExists('posts');
            }
        } finally {
            if ($this->postgresConfigured) {
                DB::setDefaultConnection('testing');
                DB::purge('projection_pgsql');
            }

            parent::tearDown();
        }
    }

    #[DataProviderExternal(ProjectionResultsTest::class, 'queries')]
    public function test_exact_numeric_results_survive_execution_and_cache_hits(string $class, string $kind): void
    {
        $query = $class::make()->withResultColumn('posts.amount');
        $native = DB::table('posts');
        $expected = match ($kind) {
            'Value' => $native->value('amount'),
            'Pluck' => $native->pluck('amount', 'id')->all(),
            'Aggregate' => $native->sum('amount'),
        };
        $this->assertIsString($kind === 'Pluck' ? $expected[1] : $expected);
        DB::enableQueryLog();
        DB::flushQueryLog();

        for ($i = 0; $i < 2; $i++) {
            $result = $query->effect();
            $this->assertSame($expected, $result instanceof Collection ? $result->all() : $result);
        }

        $this->assertCount(method_exists($query, 'getCache') ? 1 : 2, DB::getQueryLog());
    }

    #[DataProviderExternal(ProjectionResultsTest::class, 'aggregateQueries')]
    public function test_every_numeric_aggregate_and_nullable_result_preserves_native_type(string $class): void
    {
        $query = $class::make()->withResultColumn('posts.amount');

        foreach (AggregateFunction::cases() as $function) {
            $expected = DB::table('posts')->{$function->value}('amount');
            $this->assertIsString($expected);
            $query->withAggregateFunction($function);
            $this->assertSame($expected, $query->effect());
            $this->assertSame($expected, $query->effect());

            $empty = $class::make()->withResultColumn('posts.amount')->withAggregateFunction($function)
                ->addFilter(new CallbackFilter(fn (Builder $query) => $query->where('id', '<', 0)));
            $expectedEmpty = DB::table('posts')->where('id', '<', 0)->{$function->value}('amount');
            $this->assertSame($expectedEmpty, $empty->effect());
            $this->assertSame($expectedEmpty, $empty->effect());
        }
    }
}
