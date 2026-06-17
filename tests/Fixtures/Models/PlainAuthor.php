<?php

namespace Atldays\Sculptor\Tests\Fixtures\Models;

use Atldays\EloquentFilters\Filterable;
use Illuminate\Database\Eloquent\Model;

class PlainAuthor extends Model
{
    use Filterable;

    protected $table = 'authors';

    protected $guarded = [];
}
