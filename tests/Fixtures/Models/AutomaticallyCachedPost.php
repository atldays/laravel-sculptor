<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

class AutomaticallyCachedPost extends Post
{
    protected $table = 'posts';

    protected int $cacheFor = 600;

    protected string $cacheDriver = 'array';

    protected array $cacheTags = [];

    /** @return BelongsTo<AutomaticallyCachedAuthor, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(AutomaticallyCachedAuthor::class, 'author_id');
    }

    /** @return BelongsTo<CrossConnectionCachedAuthor, $this> */
    public function crossConnectionAuthor(): BelongsTo
    {
        return $this->belongsTo(CrossConnectionCachedAuthor::class, 'author_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
