<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Tests\Support\RedisCacheFixture;
use PDO;
use PHPUnit\Framework\Attributes\{DataProvider, Group};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

#[Group('redis')]
class RedisCacheLockTest extends TestCase
{
    private ?RedisCacheFixture $fixture = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!getenv('SCULPTOR_REDIS_HOST')) {
            $this->markTestSkipped('Set SCULPTOR_REDIS_HOST to run the Redis concurrency tests.');
        }

        $this->assertTrue(extension_loaded('redis'), 'The Redis concurrency tests require phpredis.');
        $this->fixture = new RedisCacheFixture('sculptor-test:' . bin2hex(random_bytes(16)));
    }

    protected function tearDown(): void
    {
        $this->fixture?->cleanup();
        parent::tearDown();
    }

    public static function strategies(): iterable
    {
        yield 'complete result and relations' => ['result'];
        yield 'underlying queries and relations' => ['builder'];
    }

    #[DataProvider('strategies')]
    public function test_concurrent_reads_share_one_fill_and_different_arguments_do_not_block(string $strategy): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sculptor-lock-');

        if ($path === false) {
            throw new RuntimeException('Could not create the concurrency test database.');
        }

        $workers = [];

        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->exec("CREATE TABLE authors (id INTEGER PRIMARY KEY, name TEXT); INSERT INTO authors VALUES (42, 'Owner'), (57, 'Other owner')");
            $pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, author_id INTEGER, title TEXT); INSERT INTO posts VALUES (1, 42, 'Shared'), (2, 57, 'Independent')");
            $pdo = null;
            $workers['producer'] = $this->worker('producer', 1, $path, $strategy);
            $this->signal('producer-in-sql');
            $workers['waiter-a'] = $this->worker('waiter-a', 1, $path, $strategy);
            $workers['waiter-b'] = $this->worker('waiter-b', 1, $path, $strategy);
            $workers['independent'] = $this->worker('independent', 2, $path, $strategy);
            $this->assertSame($this->signal('waiting:waiter-a'), $this->signal('waiting:waiter-b'));
            $other = $this->workerResult($workers['independent']);
            $this->assertSame('Independent', $other['title']);
            $this->assertSame('Other owner', $other['author']);
            $this->assertTrue($workers['producer']->isRunning());
            $this->fixture->client->rPush($this->fixture->namespace . ':finish-production', 'done');
            $pids = [$other['pid']];

            foreach (['producer', 'waiter-a', 'waiter-b'] as $role) {
                $result = $this->workerResult($workers[$role]);
                $this->assertSame('Shared', $result['title']);
                $this->assertSame('Owner', $result['author']);
                $pids[] = $result['pid'];
            }

            $this->assertCount(4, array_unique($pids));

            foreach (['posts', 'authors'] as $table) {
                foreach ([1, 2] as $id) {
                    $this->assertSame('1', $this->fixture->client->get($this->fixture->namespace . ':sql:' . $table . ':' . $id));
                }
            }
        } finally {
            foreach ($workers as $worker) {
                $worker->stop(0.2);
            }

            unlink($path);
        }
    }

    private function worker(string $role, int $id, string $path, string $strategy): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__) . '/Support/cache-lock-worker.php', $this->fixture->namespace, $role, $path, $strategy, (string)$id], timeout: 25);
        $process->start();

        return $process;
    }

    private function signal(string $name): string
    {
        $signal = $this->fixture->client->blPop([$this->fixture->namespace . ':' . $name], 8);
        $this->assertIsArray($signal, 'Timed out waiting for ' . $name);

        return $signal[1];
    }

    /** @return array{pid: int, title: string, author: string} */
    private function workerResult(Process $process): array
    {
        $process->wait();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput() . $process->getOutput());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
