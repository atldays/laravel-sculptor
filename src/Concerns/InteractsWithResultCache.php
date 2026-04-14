<?php

namespace Atldays\Sculptor\Concerns;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use JsonException;
use ReflectionFunction;

trait InteractsWithResultCache
{
    protected function rememberResult(string $kind, callable $callback): mixed
    {
        return $this->getCache()->remember(
            $this->cacheKey($kind),
            $this->cacheFor(),
            $callback
        );
    }

    /**
     * @throws JsonException
     */
    protected function cacheKey(string $kind): string
    {
        return sprintf(
            '%s:%s',
            static::class,
            sha1(json_encode($this->cachePayload($kind), JSON_THROW_ON_ERROR))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cachePayload(string $kind): array
    {
        /** @var Builder $query */
        $query = $this->query();

        return [
            'kind' => $kind,
            'class' => static::class,
            'model' => $this->model(),
            'connection' => $query->getModel()->getConnectionName(),
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'eager_loads' => $this->describeEagerLoads($query->getEagerLoads()),
        ];
    }

    /**
     * @param  array<string, callable>  $eagerLoads
     * @return array<string, array<string, mixed>>
     */
    private function describeEagerLoads(array $eagerLoads): array
    {
        ksort($eagerLoads);

        return array_map(fn (callable $constraint) => $this->describeCallable($constraint), $eagerLoads);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \ReflectionException
     */
    private function describeCallable(callable $callable): array
    {
        if ($callable instanceof Closure) {
            $reflection = new ReflectionFunction($callable);

            return [
                'type' => 'closure',
                'file' => $reflection->getFileName(),
                'start_line' => $reflection->getStartLine(),
                'end_line' => $reflection->getEndLine(),
                'static' => $this->normalizeValue($reflection->getStaticVariables()),
            ];
        }

        if (is_array($callable)) {
            return [
                'type' => 'array',
                'callable' => array_map(fn (mixed $value) => $this->normalizeValue($value), $callable),
            ];
        }

        return [
            'type' => 'string',
            'callable' => $this->normalizeValue($callable),
        ];
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_null($value) || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->normalizeValue($item), $value);
        }

        if (is_object($value)) {
            return ['object' => $value::class];
        }

        return gettype($value);
    }
}
