<?php

namespace Atldays\Sculptor\Contracts;

use Illuminate\Database\Eloquent\Model;

interface WithModel
{
    /**
     * @return class-string
     */
    public function model(): string;

    public function newModel(): Model;
}
