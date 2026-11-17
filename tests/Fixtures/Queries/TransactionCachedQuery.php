<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\{CacheFor, CacheStore};
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Contracts\WithoutCacheInTransactions;
use Atldays\Sculptor\Tests\Fixtures\Models\AutomaticallyCachedPost;
use Illuminate\Database\Eloquent\Model;

#[CacheStore('array')]
#[CacheFor(600)]
abstract class TransactionCachedQuery extends CachedQuery implements WithoutCacheInTransactions
{
    protected string $model = AutomaticallyCachedPost::class;

    /** @param class-string<Model>|null $model */
    public function __construct(?string $model = null)
    {
        if ($model !== null) {
            $this->model = $model;
        }
    }
}
