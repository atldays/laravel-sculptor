<?php

namespace Atldays\Sculptor\Contracts;

use Illuminate\Contracts\Database\Eloquent\Builder;

interface WithQuery
{
    /**
     * @return Builder
     */
    public function query(): Builder;
}
