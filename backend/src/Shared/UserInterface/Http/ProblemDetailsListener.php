<?php

declare(strict_types=1);

namespace Eventor\Shared\UserInterface\Http;

use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\UserInterface\Http\Problem\ProblemFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Turns every exception of an `/api` request into `application/problem+json` (RFC 9457). Runs after Symfony's
 * ErrorListener has logged the exception (priority 0) and before it would render its own error page (-128).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final readonly class ProblemDetailsListener
{
    public function __construct(
        private ProblemFactory $problems,
        private NormalizerInterface $normalizer,
        private AuditContext $context,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $problem = $this->problems->fromThrowable($event->getThrowable(), $this->context->correlationId());

        $response = new JsonResponse(
            $this->normalizer->normalize($problem, 'json'),
            $problem->status,
            ['Content-Type' => 'application/problem+json'],
        );
        $throwable = $event->getThrowable();
        if ($throwable instanceof HttpExceptionInterface) {
            foreach (['Retry-After', 'WWW-Authenticate'] as $header) {
                $value = $throwable->getHeaders()[$header] ?? null;
                if (\is_scalar($value)) {
                    $response->headers->set($header, (string) $value);
                }
            }
        }

        $event->setResponse($response);
    }
}
