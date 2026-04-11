<?php

namespace Atldays\Sculptor\Contracts;

use Illuminate\Contracts\Database\Eloquent\Builder;

interface WithQuery
{
    public function query(): Builder;
}
