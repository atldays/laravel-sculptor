<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomaticallyCachedAuthor extends Author
{
    protected $table = 'authors';

    protected int $cacheFor = 600;

    protected string $cacheDriver = 'array';

    protected array $cacheTags = [];

    /** @return HasMany<AutomaticallyCachedPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(AutomaticallyCachedPost::class, 'author_id');
    }
}
