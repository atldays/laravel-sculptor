<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class CacheScope {}
