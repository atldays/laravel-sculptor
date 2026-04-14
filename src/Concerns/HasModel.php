<?php

namespace Atldays\Sculptor\Concerns;

use Illuminate\Database\Eloquent\Model;
use Webmozart\Assert\Assert;

/**
 * @template TModel of Model
 *
 * @property class-string<TModel> $model
 */
trait HasModel
{
    /**
     * @return class-string<TModel>
     */
    public function model(): string
    {
        Assert::propertyExists($this, 'model');

        return $this->model;
    }

    /**
     * @return TModel&Model
     */
    public function newModel(): Model
    {
        $model = $this->model();

        return new $model;
    }
}
