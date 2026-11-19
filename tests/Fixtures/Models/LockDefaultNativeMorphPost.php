<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LockDefaultNativeMorphPost extends LockDefaultPost
{
    protected function newMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation): MorphTo
    {
        return new MorphTo($query, $parent, $foreignKey, $ownerKey, $type, $relation);
    }
}
