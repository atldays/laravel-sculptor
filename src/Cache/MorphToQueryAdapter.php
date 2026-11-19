<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Cache;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use ReflectionProperty;

/**
 * @internal
 *
 * Laravel exposes no public hooks around MorphTo's per-type constraints.
 * Keep access to its replay buffer and constraint map isolated here.
 */
final readonly class MorphToQueryAdapter
{
    /** @param MorphTo<*, *> $relation */
    public function __construct(private MorphTo $relation) {}

    /** @param MorphTo<*, *> $relation */
    public static function make(MorphTo $relation): self
    {
        return new self($relation);
    }

    /** @param Closure(Builder): void $callback */
    public function onTypeQuery(Closure $callback): void
    {
        $this->bufferTypeCallback(function (Builder $query) use ($callback): void {
            $this->afterTypeConstraints($query->getModel()::class, $callback);
        }, false);
    }

    /** @param Closure(Builder): void $callback */
    public function beforeTypeConstraints(Closure $callback): void
    {
        $this->bufferTypeCallback($callback, true);
    }

    /** @param Closure(Builder): void $callback */
    private function bufferTypeCallback(Closure $callback, bool $prepend): void
    {
        $property = new ReflectionProperty(MorphTo::class, 'macroBuffer');
        /** @var list<array{method: string, parameters: list<mixed>}> $calls */
        $calls = $property->getValue($this->relation);
        $call = [
            'method' => 'tap',
            'parameters' => [$callback],
        ];

        if ($prepend) {
            array_unshift($calls, $call);
        } else {
            $calls[] = $call;
        }

        $property->setValue($this->relation, $calls);
    }

    /**
     * @param class-string<Model> $type
     * @param Closure(Builder): void $callback
     */
    private function afterTypeConstraints(string $type, Closure $callback): void
    {
        // Read at replay time: relation subclasses may configure constraints
        // while creating the concrete model, before its query is built.
        $property = new ReflectionProperty(MorphTo::class, 'morphableConstraints');
        /** @var array<class-string<Model>, callable(Builder): mixed> $constraints */
        $constraints = $property->getValue($this->relation);
        $constraint = $constraints[$type] ?? null;
        $this->relation->constrain([
            $type => static function (Builder $query) use ($constraint, $callback): void {
                if ($constraint !== null) {
                    $constraint($query);
                }

                $callback($query);
            },
        ]);
    }
}
