<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Support;

use Closure;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Redis;
use RuntimeException;

final readonly class RedisCacheFixture
{
    public Redis $client;

    public function __construct(public string $namespace)
    {
        $this->client = new Redis;
        $host = getenv('SCULPTOR_REDIS_HOST');

        if ($host === false || !$this->client->connect($host, (int)(getenv('SCULPTOR_REDIS_PORT') ?: 6379), 2.0)) {
            throw new RuntimeException('Could not connect to the Sculptor test Redis service.');
        }

        $this->client->setOption(Redis::OPT_READ_TIMEOUT, 15);
    }

    /** @param null|Closure(string, bool): void $observeLock */
    public function store(?Closure $observeLock = null): RedisStore
    {
        $connection = new class($this->client, $observeLock) extends PhpRedisConnection
        {
            public function __construct(Redis $client, private ?Closure $observeLock)
            {
                parent::__construct($client);
            }

            public function command($method, array $parameters = [])
            {
                $result = parent::command($method, $parameters);
                $options = $parameters[2] ?? null;

                if ($method === 'set' && is_array($options) && in_array('NX', $options, true)) {
                    ($this->observeLock)?->__invoke($parameters[0], (bool)$result);
                }

                return $result;
            }
        };
        $factory = new class($connection) implements Factory
        {
            public function __construct(private PhpRedisConnection $connection) {}

            public function connection($name = null): PhpRedisConnection
            {
                return $this->connection;
            }
        };

        return new RedisStore($factory, $this->namespace . ':data:');
    }

    public function cleanup(): void
    {
        $cursor = null;

        do {
            $keys = $this->client->scan($cursor, $this->namespace . ':*', 100);

            if ($keys) {
                $this->client->del($keys);
            }
        } while ((int)$cursor !== 0);

        $this->client->close();
    }
}
