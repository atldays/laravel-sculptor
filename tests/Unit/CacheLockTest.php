<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Attributes\CacheLock;
use Atldays\Sculptor\Concerns\HasCache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CacheLockTest extends TestCase
{
    public function test_defaults_and_attribute_inheritance(): void
    {
        $plain = new class()
        {
            use HasCache;
        };
        $this->assertNull($plain->cacheLockOptions());
        $this->assertSame(30, (new CacheLock)->options()->seconds);
        $this->assertSame(5, (new CacheLock)->options()->waitSeconds);
        $this->assertSame(21, (new InheritedLockConsumer)->cacheLockOptions()->seconds);
        $this->assertSame(4, (new InheritedLockConsumer)->cacheLockOptions()->waitSeconds);
        $this->assertSame(9, (new OverriddenLockConsumer)->cacheLockOptions()->seconds);
    }

    public function test_runtime_override_opt_out_reenable_and_clones_are_independent(): void
    {
        $query = new InheritedLockConsumer;
        $this->assertSame($query, $query->setCacheLock(45, 0));
        $clone = clone $query;
        $this->assertSame(45, $query->cacheLockOptions()->seconds);
        $this->assertSame(0, $query->cacheLockOptions()->waitSeconds);
        $this->assertSame($query, $query->withoutCacheLock());
        $this->assertNull($query->cacheLockOptions());
        $this->assertSame(45, $clone->cacheLockOptions()->seconds);
        $this->assertSame(21, (new InheritedLockConsumer)->cacheLockOptions()->seconds);
        $this->assertSame($query, $query->setCacheLock());
        $this->assertSame(30, $query->cacheLockOptions()->seconds);
        $this->assertSame(5, $query->cacheLockOptions()->waitSeconds);
    }

    public static function invalidOptions(): iterable
    {
        yield 'zero lease' => [0, 0];
        yield 'negative lease' => [-1, 0];
        yield 'negative wait' => [1, -1];
    }

    #[DataProvider('invalidOptions')]
    public function test_attribute_rejects_invalid_options(int $seconds, int $waitSeconds): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CacheLock($seconds, $waitSeconds);
    }

    #[DataProvider('invalidOptions')]
    public function test_invalid_runtime_override_does_not_change_previous_options(int $seconds, int $waitSeconds): void
    {
        $query = (new LockConsumer)->setCacheLock(17, 0);

        try {
            $query->setCacheLock($seconds, $waitSeconds);
            $this->fail('Invalid options must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame(17, $query->cacheLockOptions()->seconds);
        }
    }
}

#[CacheLock(seconds: 21, waitSeconds: 4)]
class LockConsumer
{
    use HasCache;
}

class InheritedLockConsumer extends LockConsumer {}

#[CacheLock(seconds: 9, waitSeconds: 1)]
class OverriddenLockConsumer extends LockConsumer {}
