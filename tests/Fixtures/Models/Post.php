<?php

namespace Atldays\Sculptor\Tests\Fixtures\Models;

use Atldays\EloquentFilters\Filterable;
use Atldays\QueryCache\Traits\QueryCacheable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    use Filterable;
    use QueryCacheable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published' => 'bool',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
