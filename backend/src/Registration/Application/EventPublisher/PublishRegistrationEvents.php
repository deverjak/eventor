<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\EventPublisher;

use Eventor\Registration\Contract\Event\AnswersIncomplete;
use Eventor\Registration\Contract\Event\LatePaymentReceived;
use Eventor\Registration\Contract\Event\OrderAmountChanged;
use Eventor\Registration\Contract\Event\OrderPlaced;
use Eventor\Registration\Contract\Event\ParticipationCancelled;
use Eventor\Registration\Contract\Event\ParticipationExpired;
use Eventor\Registration\Contract\Event\ParticipationRequiresAttention;
use Eventor\Registration\Contract\Event\ParticipationRestored;
use Eventor\Registration\Contract\Event\ParticipationsConfirmed;
use Eventor\Registration\Contract\Event\SelfServiceLinkRequested;
use Eventor\Registration\Domain\Event\AnswersWereFoundIncomplete;
use Eventor\Registration\Domain\Event\LatePaymentWasReceived;
use Eventor\Registration\Domain\Event\OrderAmountDueChanged;
use Eventor\Registration\Domain\Event\OrderWasPlaced;
use Eventor\Registration\Domain\Event\ParticipationNeedsAttention;
use Eventor\Registration\Domain\Event\ParticipationsWereConfirmed;
use Eventor\Registration\Domain\Event\ParticipationWasCancelled;
use Eventor\Registration\Domain\Event\ParticipationWasExpired;
use Eventor\Registration\Domain\Event\ParticipationWasRestored;
use Eventor\Registration\Domain\Event\SelfServiceLinkWasRequested;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Domain event -> integration event (chapter 06). Synchronous, in the command transaction; the integration event goes
 * into the outbox with the tenant stamp. One method per fact; ids, amounts and enums only, no personal data.
 */
final readonly class PublishRegistrationEvents
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    #[AsMessageHandler(bus: 'event.bus')]
    public function onOrderPlaced(OrderWasPlaced $e): void
    {
        $this->publish($e->organizerId, new OrderPlaced($e->organizerId, $e->orderId, $e->orderNumber, $e->eventId, $e->paymentMethod, $e->amountDue, $e->participationIds, $e->holdExpiresAt, $this->now()));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onConfirmed(ParticipationsWereConfirmed $e): void
    {
        $this->publish($e->organizerId, new ParticipationsConfirmed($e->organizerId, $e->orderId, $e->participationIds, $e->reason, $this->now()));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onExpired(ParticipationWasExpired $e): void
    {
        $this->publish($e->organizerId, new ParticipationExpired($e->organizerId, $e->orderId, $e->participationId, $e->bucketIds, $this->now()));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onCancelled(ParticipationWasCancelled $e): void
    {
        $this->publish($e->organizerId, new ParticipationCancelled($e->organizerId, $e->orderId, $e->participationId, $e->by, $e->reason, $this->now()));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onNeedsAttention(ParticipationNeedsAttention $e): void
    {
        $this->publish($e->organizerId, new ParticipationRequiresAttention($e->organizerId, $e->orderId, $e->participationId, $e->cause, $this->now()));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onRestored(ParticipationWasRestored $e): void
    {
        $this->publish($e->organizerId, new ParticipationRestored($e->organizerId, $e->orderId, $e->participationId, $e->by, $this->now()));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onLatePayment(LatePaymentWasReceived $e): void
    {
        $this->publish($e->organizerId, new LatePaymentReceived($e->organizerId, $e->orderId, $e->participationIds, $e->capacityAvailable, $e->autoRestore, $this->now()));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onAmountChanged(OrderAmountDueChanged $e): void
    {
        $this->publish($e->organizerId, new OrderAmountChanged($e->organizerId, $e->orderId, $e->previous, $e->new, $e->reason, $this->now()));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onAnswersIncomplete(AnswersWereFoundIncomplete $e): void
    {
        $this->publish($e->organizerId, new AnswersIncomplete($e->organizerId, $e->orderId, $e->participationIds, $e->formVersionId, $this->now()));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onLinkRequested(SelfServiceLinkWasRequested $e): void
    {
        $this->publish($e->organizerId, new SelfServiceLinkRequested($e->organizerId, $e->orderId, $e->tokenId, $this->now()));
    }

    private function publish(OrganizerId $organizer, object $event): void
    {
        $this->eventBus->dispatch($event, [TenantStamp::of($organizer)]);
    }

    private function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now());
    }
}
