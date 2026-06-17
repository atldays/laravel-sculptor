<?php

namespace Atldays\Sculptor\Attributes;

use Attribute;
use Illuminate\Database\Eloquent\Model;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class ForModel
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(protected string $model) {}

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return $this->model;
    }
}
