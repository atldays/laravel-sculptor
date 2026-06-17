<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Attributes\ForModel;
use Atldays\Sculptor\Attributes\Helpers\AttributeReader;
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
        if (property_exists($this, 'model')) {
            return $this->model;
        }

        $attribute = AttributeReader::make($this)->get(ForModel::class);

        if ($attribute instanceof ForModel) {
            /** @var class-string<TModel> $model */
            $model = $attribute->model();

            return $model;
        }
        Assert::notNull(null, sprintf(
            'Expected the property "model" or [%s] attribute to exist.',
            ForModel::class,
        ));
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
