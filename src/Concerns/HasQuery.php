<?php

namespace Atldays\Sculptor\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @property string[] $with
 */
trait HasQuery
{
    use HasModel;

    private array $columns = [];

    /**
     * @var Collection<array-key, string|callable>|null
     */
    private ?Collection $relations = null;

    private ?int $limit = null;

    /**
     * @return array|string[]|null
     */
    public function select(): ?array
    {
        if (! empty($this->columns)) {
            return $this->columns;
        }

        if (property_exists($this, 'select')) {
            if (is_array($select = $this->select)) {
                return $select;
            }

            return [$select];
        }

        return [$this->newModel()->qualifyColumn('*')];
    }

    /**
     * @return $this
     */
    public function withSelect(string|array $columns): static
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $this->columns = $columns;

        return $this;
    }

    /**
     * @return Collection<array-key, string|callable>
     */
    public function relations(): Collection
    {
        if ($this->relations instanceof Collection) {
            return $this->relations;
        }

        $relations = collect(property_exists($this, 'with') && is_array($this->with) ? $this->with : []);

        return $this->relations = $this->prepareRelations($relations);
    }

    /**
     * @param  string|array|Collection<array-key, string|callable>  $relations
     * @return $this
     */
    public function withRelations(string|array|Collection $relations): static
    {
        if (is_string($relations)) {
            $relations = collect([$relations]);
        } elseif (is_array($relations)) {
            $relations = collect($relations);
        }

        $this->relations = $this->prepareRelations($relations);

        return $this;
    }

    protected function prepareRelations(Collection $relations): Collection
    {
        return $relations;
    }

    /**
     * @return $this
     */
    public function resetRelations(): static
    {
        $this->relations = null;

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutRelations(): static
    {
        $this->relations = collect();

        return $this;
    }

    /**
     * @return $this
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function hasLimit(): bool
    {
        return is_int($this->limit) && $this->limit > 0;
    }

    /**
     * @return $this
     */
    public function withoutLimit(): static
    {
        $this->limit = null;

        return $this;
    }

    public function query(): Builder
    {
        $query = $this->newModel()->query()->select($this->select());

        if (($relations = $this->relations())->isNotEmpty()) {
            $query->with($relations->all());
        }

        if ($this->hasLimit()) {
            $query->limit($this->limit);
        }

        return $query;
    }
}
