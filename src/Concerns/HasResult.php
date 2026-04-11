<?php

namespace Atldays\Sculptor\Concerns;

use Illuminate\Support\Facades\App;

/**
 * @template TResult
 */
trait HasResult
{

    /**
     * @return mixed
     */
    abstract public function effect();

    /**
     * Instantiate a new class with the arguments.
     *
     * @param mixed ...$arguments
     * @return static
     */
    public static function make(mixed ...$arguments): static
    {
        return App::make(static::class, $arguments);
    }

    /**
     * @param mixed ...$args
     * @return TResult
     */
    public static function result(mixed ...$args): mixed
    {
        return debugbar_measure(static::class, fn() => static::make(...$args)->effect());
    }
}
