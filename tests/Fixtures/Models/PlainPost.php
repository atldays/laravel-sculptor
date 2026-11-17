<?php

namespace Atldays\Sculptor\Tests\Fixtures\Models;

use Atldays\EloquentFilters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlainPost extends Model
{
    use Filterable;

    protected $table = 'posts';

    protected $guarded = [];

    /** @return BelongsTo<PlainAuthor, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(PlainAuthor::class);
    }
}
