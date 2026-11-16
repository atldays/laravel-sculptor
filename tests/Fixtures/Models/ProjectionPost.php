<?php

namespace Atldays\Sculptor\Tests\Fixtures\Models;

class ProjectionPost extends PlainPost
{
    protected $casts = ['published' => 'boolean', 'created_at' => 'datetime'];

    public function getTitleAttribute(string $value): string
    {
        return strtoupper($value);
    }
}
