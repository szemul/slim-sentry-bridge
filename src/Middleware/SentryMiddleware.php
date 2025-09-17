<?php
declare(strict_types=1);

namespace Szemul\SlimSentryBridge\Middleware;

use Psr\Container\ContainerInterface;
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
use Szemul\LoggingErrorHandlingContext\ContextEntry;
use Szemul\LoggingErrorHandlingContext\ContextEntryFactory;
use Szemul\LoggingErrorHandlingContext\ContextInterface;

class SentryMiddleware implements MiddlewareInterface
{
    private bool $dsnIsSet;

    public function __construct(
        protected HubInterface $hub,
        protected SentryTracingState $tracingState,
        protected ContextInterface $context,
        protected ContextEntryFactory $contextEntryFactory,
        ConfigInterface $config,
    ) {
        $this->dsnIsSet = !empty($config->get('application.sentry.dsn', null));
    }

    /** @return array<string,mixed>|null */
    public function __debugInfo(): ?array
    {
        return [
            'dsnIsSet'            => $this->dsnIsSet,
            'hub'                 => '** Instance of ' . get_class($this->hub),
            'tracingState'        => $this->tracingState,
            'context'             => $this->context,
            'contextEntryFactory' => $this->contextEntryFactory,
        ];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->dsnIsSet) {
            return $handler->handle($request);
        }

        /** @var Route<ContainerInterface>|null $route */
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
