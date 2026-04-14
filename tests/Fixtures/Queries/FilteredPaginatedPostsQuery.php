<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Concerns\QueryResultPaginated;
use Atldays\Sculptor\Contracts\WithPaginatedResult;
use Atldays\Sculptor\Query;
use Atldays\Sculptor\Tests\Fixtures\Models\Post;
use Illuminate\Contracts\Database\Eloquent\Builder;

class FilteredPaginatedPostsQuery extends Query implements WithPaginatedResult
{
    use QueryResultPaginated;

    protected string $model = Post::class;

    public function __construct(private readonly int $minId)
    {
    }

    public function query(): Builder
    {
        return parent::query()->where('posts.id', '>=', $this->minId);
    }
}
