<?php
declare(strict_types=1);

namespace Szemul\SlimSentryBridge\Test\ShutdownHandler;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Sentry\Tracing\Span;
use Szemul\DebuggerSentryBridge\SentryTracingState;
use Szemul\SlimSentryBridge\ShutdownHandler\SentryShutdownHandler;
use PHPUnit\Framework\TestCase;

class SentryShutdownHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private SentryTracingState    $tracingState;
    private SentryShutdownHandler $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tracingState = new SentryTracingState();
        $this->sut          = new SentryShutdownHandler($this->tracingState);
    }

    public function testWithSpan_finishShouldBeCalled(): void
    {
        $this->createSpanAndExpectFinishToBeCalled();

        $this->sut->handleShutdown();
    }

    public function testWithNoSpan_finishShouldNotBeCalled(): void
    {
        $this->sut->handleShutdown();

        // Noop assert to avoid issues with phpunit
        $this->assertTrue(true); //@phpstan-ignore-line
    }

    private function createSpanAndExpectFinishToBeCalled(): void
    {
        $span = Mockery::mock(Span::class);

        // @phpstan-ignore-next-line
        $span->shouldReceive('finish')
            ->once()
            ->withNoArgs();

        $this->tracingState->setSpan($span); // @phpstan-ignore-line
    }
}
