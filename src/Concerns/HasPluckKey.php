<?php

namespace Atldays\Sculptor\Concerns;

use Atldays\Sculptor\Attributes\Helpers\AttributeReader;
use Atldays\Sculptor\Attributes\PluckKey;
use Illuminate\Contracts\Database\Query\Expression;

trait HasPluckKey
{
    private ?PluckKey $pluckKeyOverride = null;

    public function withPluckKey(string|Expression|null $key): static
    {
        $this->pluckKeyOverride = new PluckKey($key);

        return $this;
    }

    public function pluckKey(): string|Expression|null
    {
        $attribute = $this->pluckKeyOverride ?? AttributeReader::make($this)->get(PluckKey::class);

        return $attribute?->key();
    }
}
