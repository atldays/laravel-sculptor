<?php

namespace Atldays\Sculptor\Attributes;

use Attribute;
use Illuminate\Contracts\Database\Query\Expression;
use Webmozart\Assert\Assert;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class PluckKey
{
    public function __construct(protected string|Expression|null $key)
    {
        if (is_string($key)) {
            Assert::notWhitespaceOnly($key, 'Pluck key must not be empty.');
        }
    }

    public function key(): string|Expression|null
    {
        return $this->key;
    }
}
