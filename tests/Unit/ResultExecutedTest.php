<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Events\ResultExecuted;
use Atldays\Sculptor\Tests\TestCase;

class ResultExecutedTest extends TestCase
{
    public function test_duration_is_calculated_in_milliseconds(): void
    {
        $event = new ResultExecuted(
            name: 'query',
            startedAt: 1.5,
            finishedAt: 1.75,
        );

        $this->assertSame(250.0, $event->duration());
    }
}
