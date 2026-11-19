<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\CacheLock;

#[CacheLock(seconds: 23, waitSeconds: 0)]
class LockedCachedPostsQuery extends CachedAllPostsQuery {}
