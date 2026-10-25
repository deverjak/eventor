<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Audit\AuditRecord;
use Eventor\Shared\Application\Audit\AuditRecorder;
use Eventor\Shared\Application\Messaging\CommandRecordedNoDomainEvent;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Eventor\Shared\Domain\Messaging\ReadOnlyCommand;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Innermost middleware of command.bus (inside doctrine_transaction). After the handler returns it flushes, pulls
 * the domain events recorded by the aggregates and dispatches them on event.bus: synchronous handlers run in the
 * same transaction, and integration events that those handlers publish are INSERTed into the Doctrine
 * transports (the transactional outbox) before the transaction commits (ADR 003). Events that are `Auditable`
 * also leave their audit entry here.
 *
 * In dev and test it also enforces ADR 001: a command of the application that records no domain event fails,
 * unless it is a #[ReadOnlyCommand] or its handler is #[NoDomainEvent].
 */
final readonly class DomainEventsMiddleware implements MiddlewareInterface
{
    private const int MAX_ROUNDS = 50;

    public function __construct(
        private EntityManagerInterface $em,
        private DomainEventCollector $collector,
        #[Target('event.bus')]
        private MessageBusInterface $eventBus,
        private AuditRecorder $audit,
        private AuditContext $auditContext,
        private TenantContext $tenant,
        private ClockInterface $clock,
        #[Autowire('%eventor.require_domain_event%')]
        private bool $requireDomainEvent = false,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $stack->next()->handle($envelope, $stack);

        $dispatched = 0;
        for ($round = 0; $round < self::MAX_ROUNDS; ++$round) {
            $this->em->flush();
            $events = $this->collector->release();
            if ([] === $events) {
                $this->assertEmitted($envelope, $dispatched);

                return $envelope;
            }

            foreach ($events as $event) {
                ++$dispatched;
                if ($event instanceof Auditable) {
                    $this->audit($event);
                }
                $this->eventBus->dispatch($event);
            }
        }

        throw new \LogicException(\sprintf('Domain events keep producing domain events after %d rounds; check for a cycle.', self::MAX_ROUNDS));
    }

    private function audit(Auditable $event): void
    {
        $change = $event->auditChange();

        $this->audit->record(new AuditRecord(
            $change,
            $this->auditContext->actor() ?? AuditActor::system('unknown'),
            $this->auditContext->correlationId(),
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $change->organizerId ?? $this->tenant->current(),
        ));
    }

    private function assertEmitted(Envelope $envelope, int $dispatched): void
    {
        $message = $envelope->getMessage();
        if (!$this->requireDomainEvent || $dispatched > 0 || !str_starts_with($message::class, 'Eventor\\')) {
            return;
        }

        $reflection = new \ReflectionClass($message);
        if ([] !== $reflection->getAttributes(ReadOnlyCommand::class) || [] !== $reflection->getAttributes(NoDomainEvent::class)) {
            return;
        }

        foreach ($envelope->all(HandledStamp::class) as $stamp) {
            /** @var HandledStamp $stamp */
            $handlerClass = explode('::', $stamp->getHandlerName())[0];
            if (class_exists($handlerClass) && [] !== (new \ReflectionClass($handlerClass))->getAttributes(NoDomainEvent::class)) {
                return;
            }
        }

        throw CommandRecordedNoDomainEvent::for($message::class);
    }
}
