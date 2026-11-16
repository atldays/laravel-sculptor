<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\{CacheFor, CacheScope};
use Atldays\Sculptor\Tests\Fixtures\Data\OwnerScopeData;
use Illuminate\Contracts\Database\Eloquent\Builder;

#[CacheFor(600)]
class DataScopedBuilderPostsQuery extends BuilderCachedAllPostsQuery
{
    public static int $constructions = 0;

    public function __construct(
        #[CacheScope] private readonly OwnerScopeData $owner,
        #[CacheScope] private readonly ?bool $published = null,
    ) {
        self::$constructions++;
        $this->mergeCacheTags('projects');
    }

    public function query(): Builder
    {
        $query = parent::query()->where('author_id', $this->owner->ownerId);

        if ($this->published !== null) {
            $query->where('published', $this->published);
        }

        return $query->orderBy('id');
    }
}
