<?php

namespace Atldays\Sculptor\Tests\Fixtures\Queries;

use Atldays\Sculptor\Tests\Fixtures\Filters\CallbackFilter;
use Illuminate\Database\Eloquent\Builder;

class OwnerBuilderCachedPostsQuery extends BuilderCachedAllPostsQuery
{
    public function __construct(int $ownerId)
    {
        $this->addFilter(new CallbackFilter(fn (Builder $query) => $query->where('author_id', $ownerId)))
            ->setCacheTags('projects', "projects:user:{$ownerId}");
    }
}
