<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Support;

use ReflectionAttribute;
use ReflectionClass;

readonly class AttributeReader
{
    /** @var ReflectionClass<object> */
    private ReflectionClass $class;

    /** @param class-string|object $class */
    public static function make(string|object $class): self
    {
        return new self($class);
    }

    /** @param class-string|object $class */
    public function __construct(string|object $class)
    {
        $this->class = new ReflectionClass($class);
    }

    /**
     * @template TAttribute of object
     *
     * @param class-string<TAttribute> $attribute
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
