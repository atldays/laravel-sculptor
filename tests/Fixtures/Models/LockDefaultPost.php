<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Models;

use Atldays\QueryCache\Cache\CacheLockOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LockDefaultPost extends AutomaticallyCachedPost
{
    protected $with = ['author'];

    protected function cacheLockOptionsValue(): CacheLockOptions
    {
        return new CacheLockOptions(37, 0);
    }

    /** @return BelongsTo<LockDefaultAuthor, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(LockDefaultAuthor::class, 'author_id');
    }
}
