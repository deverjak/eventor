<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Runtime;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

/**
 * Symfony Runtime for FrankenPHP worker mode. The kernel stays in memory between requests; Symfony resets
 * every `kernel.reset` service (TenantContext, AuditContext, Doctrine, ...) before the next request, which is why
 * all request-scoped state in Eventor implements ResetInterface.
 *
 * Selected with APP_RUNTIME=Eventor\Shared\Infrastructure\Runtime\FrankenPhpRuntime (docker/php/Caddyfile.worker).
 * Replaces runtime/frankenphp-symfony, which does not support Symfony 8 yet.
 */
final class FrankenPhpRuntime extends SymfonyRuntime
{
    public function getRunner(?object $application): RunnerInterface
    {
        if ($application instanceof HttpKernelInterface && \function_exists('frankenphp_handle_request')) {
            $max = $_SERVER['FRANKENPHP_MAX_REQUESTS'] ?? 500;

            return new FrankenPhpWorkerRunner($application, is_numeric($max) ? (int) $max : 500);
        }

        return parent::getRunner($application);
    }
}
