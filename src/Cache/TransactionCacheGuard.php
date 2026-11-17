<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Cache;

use Atldays\QueryCache\Query\Builder as CacheQueryBuilder;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{MorphTo, Relation};

/**
 * @internal
 *
 * A single builder context; preparation never changes the query object's settings.
 */
final readonly class TransactionCacheGuard
{
    public function __construct(private Builder $builder) {}

    public static function make(Builder $builder): self
    {
        return new self($builder);
    }

    public function prepare(bool $force = false): Builder
    {
        $query = (clone $this->query())->applyScopes()->withoutGlobalScopes();
        $query->getQuery()->applyBeforeQueryCallbacks();
        $baseQuery = $query->getQuery();
        self::make($query)->bypass($force || $baseQuery->lock !== null || $baseQuery->useWritePdo);

        return $query;
    }

    public function bypass(bool $force = false): bool
    {
        if (!$force && $this->query()->getQuery()->getConnection()->transactionLevel() === 0) {
            return false;
        }

        $this->disableCache();

        return true;
    }

    private function query(): Builder
    {
        return $this->builder instanceof Relation ? $this->builder->getQuery() : $this->builder;
    }

    private function disableCache(): void
    {
        $this->disableBaseCache();
        $query = $this->query();
        $eagerLoads = [];

        foreach ($query->getEagerLoads() as $name => $constraint) {
            $eagerLoads[$name] = static function (Builder $relation) use ($constraint): void {
                $constraint($relation);
                self::make($relation)->guardRelation();
            };
        }

        $query->setEagerLoads($eagerLoads);
    }

    private function guardRelation(): void
    {
        $this->disableBaseCache();
        $this->guardMorphTypes();

        // Run after relation constraints and model scopes, including their callbacks.
        $this->query()->withGlobalScope(self::class, static function (Builder $query): void {
            $query->getQuery()->applyBeforeQueryCallbacks();
            self::make($query)->disableCache();
        });
    }

    private function disableBaseCache(): void
    {
        $baseQuery = $this->query()->getQuery();

        if ($baseQuery instanceof CacheQueryBuilder) {
            $baseQuery->dontCache();
        }
    }

    /**
     * MorphTo creates a query per type without carrying custom scopes to it.
     * Replay a guard without replacing morph-specific constraints.
     */
    private function guardMorphTypes(): void
    {
        if (!$this->builder instanceof MorphTo) {
            return;
        }

        MorphToQueryAdapter::make($this->builder)->onTypeQuery(static function (Builder $query): void {
            self::make($query)->guardRelation();
        });
    }
}
