<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\CacheScope;
use Illuminate\Contracts\Database\Eloquent\Builder;

class CompositeScopedCachedPostsQuery extends CachedAllPostsQuery
{
    public function __construct(
        #[CacheScope]
        private readonly int $ownerId,
        #[CacheScope]
        private readonly ?bool $published = null,
    ) {
        $this->setCacheFor(120)->mergeCacheTags('projects');
    }

    public function query(): Builder
    {
        $query = parent::query()->where('author_id', $this->ownerId);

        if ($this->published !== null) {
            $query->where('published', $this->published);
        }

        return $query->orderBy('id');
    }
}
