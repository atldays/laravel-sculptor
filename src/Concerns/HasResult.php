<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Events\ResultExecuted;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Events\Dispatcher;
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
     * @throws BindingResolutionException
     */
    public static function make(mixed ...$arguments): static
    {
        return App::make(static::class, $arguments);
    }

    /**
     * @param mixed ...$args
     * @return TResult
     * @throws BindingResolutionException
     */
    public static function result(mixed ...$args): mixed
    {
        $name = static::class;
        $startedAt = microtime(true);

        try {
            return static::make(...$args)->effect();
        } finally {
            $finishedAt = microtime(true);

            if (App::bound(Dispatcher::class)) {
                App::make(Dispatcher::class)->dispatch(new ResultExecuted(
                    name: $name,
                    startedAt: $startedAt,
                    finishedAt: $finishedAt,
                ));
            }
        }
    }
}
