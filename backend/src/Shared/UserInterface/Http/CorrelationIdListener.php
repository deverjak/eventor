<?php

declare(strict_types=1);

namespace Eventor\Shared\UserInterface\Http;

use Eventor\Shared\Application\Audit\AuditContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

/**
 * The correlation id is born at the edge: `X-Request-Id` of the caller if it is sane, else a fresh UUID. It is
 * echoed in the response, written to every log line and carried by every Messenger message (CorrelationStamp).
 */
final readonly class CorrelationIdListener
{
    public const string HEADER = 'X-Request-Id';

    public function __construct(private AuditContext $context) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 255)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $supplied = $event->getRequest()->headers->get(self::HEADER);
        $id = \is_string($supplied) && 1 === preg_match('/^[A-Za-z0-9._-]{8,64}$/D', $supplied)
            ? $supplied
            : Uuid::v7()->toRfc4122();

        $this->context->start(null, $id);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -255)]
    public function onResponse(ResponseEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getResponse()->headers->set(self::HEADER, $this->context->correlationId());
        }
    }
}
