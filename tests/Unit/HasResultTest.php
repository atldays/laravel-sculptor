<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Events\ResultExecuted;
use Atldays\Sculptor\Tests\Fixtures\Queries\ParameterizedResult;
use Atldays\Sculptor\Tests\Fixtures\Queries\ThrowingResult;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use RuntimeException;

class HasResultTest extends TestCase
{
    public function test_make_resolves_class_through_container_arguments(): void
    {
        $instance = ParameterizedResult::make(value: 'resolved');

        $this->assertSame('resolved', $instance->effect());
    }

    public function test_result_returns_effect_and_dispatches_event(): void
    {
        Event::fake([ResultExecuted::class]);

        $result = ParameterizedResult::result(value: 'done');

        $this->assertSame('done', $result);

        Event::assertDispatched(ResultExecuted::class, function (ResultExecuted $event) {
            return $event->name === ParameterizedResult::class
                && $event->finishedAt >= $event->startedAt
                && $event->duration() >= 0;
        });
    }

    public function test_result_dispatches_event_even_when_effect_throws(): void
    {
        Event::fake([ResultExecuted::class]);

        try {
            ThrowingResult::result();
            $this->fail('Expected runtime exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Boom', $exception->getMessage());
        }

        Event::assertDispatched(ResultExecuted::class, fn (ResultExecuted $event) => $event->name === ThrowingResult::class);
    }
}
