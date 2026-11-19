<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Cache;

use Atldays\QueryCache\Cache\CacheLockOptions;
use Atldays\QueryCache\Query\Builder as CacheQueryBuilder;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\{Builder as EloquentBuilder, Model};
use Illuminate\Database\Eloquent\Relations\{MorphTo, Relation};
use Webmozart\Assert\Assert;

/** @internal */
final readonly class CacheLockRelations
{
    public function __construct(private Builder $builder, private ?CacheLockOptions $options) {}

    public function prepare(): void
    {
        $query = $this->query();
        $base = $query->getQuery();
        $options = $base instanceof CacheQueryBuilder ? $base->getCacheLockOptions() : $this->options;
        $eagerLoads = [];

        foreach ($query->getEagerLoads() as $name => $constraint) {
            // Dotted loads are prepared on their parent after its overrides run.
            if (str_contains($name, '.')) {
                $eagerLoads[$name] = $constraint;

                continue;
            }

            $eagerLoads[$name] = static function (Builder $relation) use ($constraint, $options): void {
                $policy = new self($relation, $options);
                $policy->configure();
                $constraint($relation);
                $policy->prepare();
            };
        }

        $query->setEagerLoads($eagerLoads);
        $this->prepareMorphTypes();
    }

    private function configure(): void
    {
        $base = $this->query()->getQuery();

        if (!$base instanceof CacheQueryBuilder) {
            return;
        }

        if ($this->options !== null) {
            $base->cacheLock($this->options->seconds, $this->options->waitSeconds);
        } else {
            $base->withoutCacheLock();
        }
    }

    private function prepareMorphTypes(): void
    {
        if (!$this->builder instanceof MorphTo) {
            return;
        }

        $source = $this->query()->getQuery();

        $adapter = MorphToQueryAdapter::make($this->builder);
        $adapter->beforeTypeConstraints(function (Builder $query) use ($source): void {
            $base = (new self($query, $this->options))->query()->getQuery();

            if ($base instanceof CacheQueryBuilder) {
                $options = $source instanceof CacheQueryBuilder ? $source->getCacheLockOptions() : $this->options;

                if ($options !== null) {
                    $base->cacheLock($options->seconds, $options->waitSeconds);
                } else {
                    $base->withoutCacheLock();
                }
            }
        });
        $adapter->onTypeQuery(function (Builder $query): void {
            (new self($query, $this->options))->prepare();
        });
    }

    /** @return EloquentBuilder<Model> */
    private function query(): EloquentBuilder
    {
        $query = $this->builder instanceof Relation ? $this->builder->getQuery() : $this->builder;
        Assert::isInstanceOf($query, EloquentBuilder::class);

        return $query;
    }
}
