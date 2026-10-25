<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Runtime;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * The request loop of a FrankenPHP worker. After `$maxRequests` the worker exits and FrankenPHP starts a fresh one
 * (guards against slow memory growth).
 */
final readonly class FrankenPhpWorkerRunner implements RunnerInterface
{
    public function __construct(
        private HttpKernelInterface $kernel,
        private int $maxRequests,
    ) {}

    public function run(): int
    {
        $request = null;
        $response = null;
        $handler = function () use (&$request, &$response): void {
            $request = Request::createFromGlobals();
            $response = $this->kernel->handle($request);
            $response->send();
        };

        $served = 0;
        do {
            $continue = frankenphp_handle_request($handler);

            if ($this->kernel instanceof TerminableInterface && $request instanceof Request && $response instanceof Response) {
                $this->kernel->terminate($request, $response);
            }
            $request = $response = null;
            gc_collect_cycles();
        } while ($continue && ($this->maxRequests <= 0 || ++$served < $this->maxRequests));

        return 0;
    }
}
