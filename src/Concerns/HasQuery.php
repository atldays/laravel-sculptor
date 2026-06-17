<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Attributes\Helpers\AttributeReader;
use Atldays\Sculptor\Attributes\Limit;
use Atldays\Sculptor\Attributes\Select;
use Atldays\Sculptor\Attributes\WithRelations;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @property string[] $with
 */
trait HasQuery
{
    use HasModel;

    private ?array $columns = null;

    /**
     * @var Collection<array-key, string|callable>|null
     */
    private ?Collection $relations = null;

    /**
     * Query limit state:
     * - null means no runtime override, so the #[Limit] attribute may be used.
     * - int means an explicit runtime limit was set through limit().
     * - false means the limit was explicitly disabled through withoutLimit().
     */
    private int|false|null $limit = null;

    /**
     * @return array|string[]|null
     */
    public function select(): ?array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        if (property_exists($this, 'select')) {
            if (is_array($select = $this->select)) {
                return $select;
            }

            return [$select];
        }

        $attribute = AttributeReader::make($this)->get(Select::class);

        if ($attribute instanceof Select) {
            return $attribute->columns();
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

        $relations = collect($this->defaultRelations());

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
        $limit = $this->effectiveLimit();

        return is_int($limit) && $limit > 0;
    }

    /**
     * @return $this
     */
    public function withoutLimit(): static
    {
        $this->limit = false;

        return $this;
    }

    public function query(): Builder
    {
        $query = $this->newModel()->query()->select($this->select());

        if (($relations = $this->relations())->isNotEmpty()) {
            $query->with($relations->all());
        }

        if (($limit = $this->effectiveLimit()) !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query;
    }

    /**
     * @return array<array-key, string|callable>
     */
    private function defaultRelations(): array
    {
        if (property_exists($this, 'with') && is_array($this->with)) {
            return $this->with;
        }

        $attribute = AttributeReader::make($this)->get(WithRelations::class);

        if ($attribute instanceof WithRelations) {
            return $attribute->relations();
        }

        return [];
    }

    private function effectiveLimit(): ?int
    {
        if (is_int($this->limit)) {
            return $this->limit;
        }

        if ($this->limit === false) {
            return null;
        }

        $attribute = AttributeReader::make($this)->get(Limit::class);

        if ($attribute instanceof Limit) {
            return $attribute->limit();
        }

        return null;
    }
}
