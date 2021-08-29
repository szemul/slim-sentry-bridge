<?php
declare(strict_types=1);

namespace Szemul\SlimSentryBridge\ShutdownHandler;

use Szemul\DebuggerSentryBridge\SentryTracingState;
use Szemul\ErrorHandler\ShutdownHandler\ShutdownHandlerInterface;

class SentryShutdownHandler implements ShutdownHandlerInterface
{
    public function __construct(private SentryTracingState $tracingState)
    {
    }

    public function handleShutdown(): void
    {
        $this->tracingState->getSpan()?->finish();
        $this->tracingState->getTransaction()?->finish();
    }
}
