<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Models;

class CrossConnectionCachedAuthor extends AutomaticallyCachedAuthor
{
    protected $connection = 'other';
}
