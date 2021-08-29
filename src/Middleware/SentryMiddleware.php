<?php
declare(strict_types=1);

namespace Szemul\SlimSentryBridge\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\State\HubInterface;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Slim\Routing\Route;
use Szemul\Config\ConfigInterface;
use Szemul\DebuggerSentryBridge\SentryTracingState;
use Szemul\LoggingErrorHandling\Context\ContextEntry;
use Szemul\LoggingErrorHandling\Context\ContextEntryFactory;
use Szemul\LoggingErrorHandling\Context\ContextInterface;

class SentryMiddleware implements MiddlewareInterface
{
    private ?string $dsn;

    public function __construct(
        protected HubInterface $hub,
        protected SentryTracingState $tracingState,
        protected ContextInterface $context,
        protected ContextEntryFactory $contextEntryFactory,
        ConfigInterface $config,
    ) {
        $this->dsn = $config->get('application.sentry.dsn', null);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (empty($this->dsn)) {
            return $handler->handle($request);
        }

        /** @var Route|null $route */
        $route = $request->getAttribute('__route__');

        $routeName = $route?->getName() ?? $request->getRequestTarget();

        $requestStart = $request->getServerParams()['REQUEST_TIME_FLOAT'] ?? microtime(true);

        $this->context->addValues(
            new ContextEntry(
                'http.route',
                $routeName,
                ContextEntry::ERROR_HANDLER_TYPE_TAG,
                [ContextEntry::SCOPE_ERROR_HANDLER, ContextEntry::SCOPE_LOG],
            ),
        );
        $transaction = $this->hub->startTransaction(new TransactionContext($routeName));
        $this->tracingState->setTransaction($transaction);
        $transaction->setStartTimestamp($requestStart);

        $spanContext = new SpanContext();
        $spanContext->setOp('http.bootstrap');
        $spanContext->setStartTimestamp($requestStart);
        $transaction->startChild($spanContext)->finish(microtime(true));

        $spanContext = new SpanContext();
        $spanContext->setOp('http.action');
        $actionSpan = $transaction->startChild($spanContext);
        $this->tracingState->setSpan($actionSpan);

        $request = $request->withAttribute('sentryActionSpan', $actionSpan);

        $result = $handler->handle($request);

        $actionSpan->finish();
        $spanContext = new SpanContext();
        $spanContext->setOp('http.emit');

        $postActionSpan = $transaction->startChild($spanContext);
        $this->tracingState->setSpan($postActionSpan);

        return $result;
    }
}
