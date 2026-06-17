<?php

namespace Atldays\Sculptor\Attributes\Helpers;

use ReflectionAttribute;
use ReflectionClass;

readonly class AttributeReader
{
    private ReflectionClass $class;

    public static function make(string|object $class): self
    {
        return new self($class);
    }

    public function __construct(string|object $class)
    {
        $this->class = new ReflectionClass($class);
    }

    /**
     * @template TAttribute of object
     *
     * @param  class-string<TAttribute>  $attribute
     * @return TAttribute|null
     */
    public function get(string $attribute): ?object
    {
        $class = $this->class;

        do {
            $attributes = $class->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        } while ($class = $class->getParentClass());

        return null;
    }
}
