<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Support;

use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\Lock;

class RecordingLockStore extends ArrayStore
{
    /** @var list<array{string, int}> */
    public array $locksRequested = [];

    /** @var list<string> */
    private array $resultKeys = [];

    public function lock($name, $seconds = 0, $owner = null): Lock
    {
        $this->locksRequested[] = [$name, $seconds];

        return parent::lock($name, $seconds, $owner);
    }

    public function put($key, $value, $seconds): bool
    {
        if (!str_starts_with($key, 'tag:')) {
            $this->resultKeys[] = $key;
        }

        return parent::put($key, $value, $seconds);
    }

    public function forgetResults(): void
    {
        foreach ($this->resultKeys as $key) {
            $this->forget($key);
        }

        $this->resultKeys = [];
    }
}
