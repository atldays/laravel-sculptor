<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Fixtures\Enums;

enum ScopeStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
