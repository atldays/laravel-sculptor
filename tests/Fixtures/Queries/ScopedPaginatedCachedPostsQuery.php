<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Attributes\CacheScope;
use Atldays\Sculptor\CachedQuery;
use Atldays\Sculptor\Concerns\QueryResultPaginated;
use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Atldays\Sculptor\Tests\Fixtures\Models\PlainPost;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ScopedPaginatedCachedPostsQuery extends CachedQuery implements WithPaginatedResult
{
    use QueryResultPaginated;

    protected string $model = PlainPost::class;

    public static int $constructions = 0;

    public function __construct(
        #[CacheScope]
        private readonly int $ownerId,
        #[CacheScope] private readonly ?bool $published = null,
    ) {
        self::$constructions++;
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
