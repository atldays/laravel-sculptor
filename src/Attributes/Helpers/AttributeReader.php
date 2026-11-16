<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Attributes\Helpers;

use Atldays\Sculptor\Support\AttributeReader as SupportAttributeReader;

/**
 * @deprecated Use \Atldays\Sculptor\Support\AttributeReader instead.
 */
readonly class AttributeReader extends SupportAttributeReader
{
    /** @param class-string|object $class */
    public static function make(string|object $class): self
    {
        return new self($class);
    }
}
