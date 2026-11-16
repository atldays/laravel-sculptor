<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Attributes\ResultColumn;
use Atldays\Sculptor\Support\AttributeReader;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Webmozart\Assert\Assert;

trait HasResultColumn
{
    private ?ResultColumn $resultColumnOverride = null;

    public function withResultColumn(string|Expression $column): static
    {
        $this->resultColumnOverride = new ResultColumn($column);

        return $this;
    }

    public function resultColumn(): string|Expression
    {
        $attribute = $this->resultColumnOverride ?? AttributeReader::make($this)->get(ResultColumn::class);
        Assert::isInstanceOf($attribute, ResultColumn::class, 'A result column is required. Use #[ResultColumn] or withResultColumn().');

        return $attribute->column();
    }

    /**
     * @param list<string|Expression> $columns
     */
    protected function resultProjectionQuery(array $columns): Builder
    {
        $query = (clone $this->query())->applyScopes()->withoutGlobalScopes();

        return $query->withoutEagerLoads()->select($columns);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resultColumnContext(string|Expression $column, Builder $query): array
    {
        return $column instanceof Expression
            ? ['expression' => $query->getQuery()->getGrammar()->getValue($column)]
            : ['column' => $column];
    }
}
