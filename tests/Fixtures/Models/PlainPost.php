<?php

namespace Atldays\Sculptor\Tests\Fixtures\Models;

use Atldays\EloquentFilters\Filterable;
use Illuminate\Database\Eloquent\Model;

class PlainPost extends Model
{
    use Filterable;

    protected $table = 'posts';

    protected $guarded = [];
}
