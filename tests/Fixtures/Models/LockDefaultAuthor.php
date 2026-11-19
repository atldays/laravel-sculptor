<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Models;

use Atldays\QueryCache\Cache\CacheLockOptions;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LockDefaultAuthor extends AutomaticallyCachedAuthor
{
    protected function cacheLockOptionsValue(): CacheLockOptions
    {
        return new CacheLockOptions(43, 0);
    }

    /** @return HasMany<LockDefaultPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(LockDefaultPost::class, 'author_id');
    }
}
