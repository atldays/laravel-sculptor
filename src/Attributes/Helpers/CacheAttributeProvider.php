<?php

namespace Atldays\Sculptor\Attributes\Helpers;

use Atldays\Sculptor\Attributes\Contracts\CacheStoreAttribute;
use Atldays\Sculptor\Attributes\DefaultCacheStore;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use ReflectionException;

readonly class CacheAttributeProvider
{
    /**
     * @var ReflectionClass
     */
    protected ReflectionClass $class;

    /**
     * @param string|ReflectionClass $class
     * @return self
     * @throws ReflectionException
     */
    public static function make(string|ReflectionClass $class): self
    {
        return new self($class);
    }

    /**
     * @param string|ReflectionClass $class
     * @return CacheContract
     * @throws ReflectionException
     */
    public static function resolve(string|ReflectionClass $class): CacheContract
    {
        return self::make($class)->cache();
    }

    /**
     * @throws ReflectionException
     */
    public function __construct(string|ReflectionClass $class)
    {
        $this->class = $class instanceof ReflectionClass ? $class : new ReflectionClass($class);
    }

    /**
     * @return string
     */
    public function store(): string
    {
        $attribute = $this->find($this->class) ?: new DefaultCacheStore();

        return $attribute->store();
    }

    /**
     * @return CacheContract
     */
    public function cache(): CacheContract
    {
        return Cache::store($this->store());
    }

    /**
     * @param ReflectionClass $class
     * @return CacheStoreAttribute|null
     */
    private function find(ReflectionClass $class): ?CacheStoreAttribute
    {
        foreach ($class->getAttributes() as $attribute) {
            if (is_subclass_of($attribute->getName(), CacheStoreAttribute::class)) {
                $instance = $attribute->newInstance();

                if ($instance instanceof CacheStoreAttribute) {
                    return $instance;
                }
            }
        }

        if ($parent = $class->getParentClass()) {
            return $this->find($parent);
        }

        return null;
    }
}
