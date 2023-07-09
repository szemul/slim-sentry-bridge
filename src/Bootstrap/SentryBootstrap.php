<?php
declare(strict_types=1);

namespace Szemul\SlimSentryBridge\Bootstrap;

use Psr\Container\ContainerInterface;
use Szemul\Config\ConfigInterface;
use Szemul\ErrorHandler\ErrorHandlerRegistry;
use Szemul\ErrorHandler\ShutdownHandlerRegistry;
use Szemul\Bootstrap\BootstrapInterface;
use Szemul\SentryErrorHandler\SentryErrorHandler;
use Szemul\SlimSentryBridge\ShutdownHandler\SentryShutdownHandler;

use function Sentry\init;

class SentryBootstrap implements BootstrapInterface
{
    public function __invoke(ContainerInterface $container): void
    {
        /** @var ConfigInterface $config */
        $config = $container->get(ConfigInterface::class);

        $sentryOptions = [
            'dsn'                  => $config->get('application.sentry.dsn'),
            'traces_sample_rate'   => $config->get('application.sentry.tracesSampleRate'),
            'environment'          => $config->get('system.environment'),
            'release'              => $config->get('system.releaseVersion', null),
        ];

        init($sentryOptions);

        /** @var ShutdownHandlerRegistry $shutdownHandlerRegistry */
        $shutdownHandlerRegistry = $container->get(ShutdownHandlerRegistry::class);
        $shutdownHandlerRegistry->addShutdownHandler($container->get(SentryShutdownHandler::class));

        /** @var ErrorHandlerRegistry $errorHandlerRegistry */
        $errorHandlerRegistry = $container->get(ErrorHandlerRegistry::class);
        $errorHandlerRegistry->addErrorHandler($container->get(SentryErrorHandler::class));
    }
}
