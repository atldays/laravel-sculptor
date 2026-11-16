<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Contracts\WithPaginationData;
use Atldays\Sculptor\Support\CachedNull;
use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use JsonException;
use ReflectionFunction;

trait InteractsWithResultCache
{
    /**
     * @param  array<string, mixed>  $context
     *
     * @throws JsonException
     */
    protected function rememberResult(string $kind, callable $callback, array $context = [], ?Builder $query = null): mixed
    {
        $query = $this->prepareResultCacheQuery($query ?? $this->query());
        $baseQuery = $query->getQuery();
        $cacheFor = $this->cacheFor();

        if ((is_int($cacheFor) && $cacheFor <= 0) || $baseQuery->lock !== null || $baseQuery->useWritePdo) {
            return $callback($query);
        }

        $result = $this->getCache()->remember(
            $this->cacheKey($kind, $context, $query),
            $cacheFor,
            fn () => $callback($query) ?? CachedNull::Value
        );

        return $result === CachedNull::Value ? null : $result;
    }

    private function prepareResultCacheQuery(Builder $query): Builder
    {
        // Apply scopes and callbacks once, before deciding whether a cache hit is safe.
        $query = (clone $query)->applyScopes()->withoutGlobalScopes();
        $query->getQuery()->applyBeforeQueryCallbacks();

        return $query;
    }

    /**
     * @param  array<string, mixed>  $context
     *
     * @throws JsonException
     */
    protected function cacheKey(string $kind, array $context = [], ?Builder $query = null): string
    {
        $query ??= $this->prepareResultCacheQuery($this->query());
        $payload = array_replace($this->cachePayload($kind, $query), $context);

        return sprintf(
            '%s:%s',
            static::class,
            sha1(json_encode($payload, JSON_THROW_ON_ERROR))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cachePayload(string $kind, Builder $query): array
    {
        // SQL grammars may mutate columns, bindings, and offsets while compiling.
        $query = clone $query;

        return [
            'kind' => $kind,
            'class' => static::class,
            'model' => $this->model(),
            'connection' => $query->getModel()->getConnectionName(),
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'eager_loads' => $this->describeEagerLoads($query->getEagerLoads()),
            'pagination' => $this instanceof WithPaginationData ? $this->paginationData()->toArray() : null,
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
