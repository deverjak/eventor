<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Delivery;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\DeliveryState;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Event\DeliveryChanged;
use Eventor\Communication\Domain\Event\DeliveryFailedPermanently;
use Eventor\Communication\Domain\Event\DeliveryRetried;
use Eventor\Communication\Domain\Exception\DeliveryNotRetryable;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * One message to one recipient (COM-06). The row is the idempotency anchor (`idempotencyKey` is unique, so a redelivered
 * event or a double click never creates a second message) and the history shown to the organizer. It stores references
 * (`orderId`, `activityId`, `refId`, `params`) from which the text is rendered at send time, never an address: the
 * contact is read from its owner when the message goes out, and only a masked hint is kept.
 *
 * Lifecycle (state machine `delivery`, services.php): scheduled -> sent | skipped | failed, failed -> scheduled (retry),
 * sent -> delivered (provider callback, P1). The state setter is the workflow's marking store.
 */
#[ORM\Entity]
#[ORM\Table(name: 'delivery', schema: 'communication')]
#[ORM\UniqueConstraint(name: 'delivery_idempotency_key_unique', columns: ['idempotency_key'])]
class Delivery implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    /** The first attempt plus four retries (1 min, 5 min, 30 min, 2 h): chapter 06, "E-mail (SMTP)". */
    public const int MAX_ATTEMPTS = 5;

    /**
     * @param array<string, scalar|null> $params neutral values the template needs (an amount, a reason code...)
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'delivery_id')]
        private DeliveryId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'campaign_id', nullable: true)]
        private ?CampaignId $campaignId,
        #[ORM\Column(name: 'trigger_key', type: 'string', length: 64)]
        private string $trigger,
        #[ORM\Column(name: 'template_key', type: 'string', length: 64, enumType: TemplateKey::class)]
        private TemplateKey $templateKey,
        #[ORM\Column(type: 'string', length: 16, enumType: CampaignPurpose::class)]
        private CampaignPurpose $purpose,
        #[ORM\Column(type: 'event_id', nullable: true)]
        private ?EventId $eventId,
        #[ORM\Column(type: 'order_id', nullable: true)]
        private ?OrderId $orderId,
        #[ORM\Column(type: 'activity_id', nullable: true)]
        private ?ActivityId $activityId,
        #[ORM\Column(type: 'string', length: 64, nullable: true)]
        private ?string $refId,
        #[ORM\Column(type: 'json', options: ['jsonb' => true])]
        private array $params,
        #[ORM\Column(type: 'string', length: 255)]
        private string $idempotencyKey,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $sendAfter,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $updatedAt,
        #[ORM\Column(type: 'string', length: 16, enumType: DeliveryState::class)]
        private DeliveryState $state = DeliveryState::Scheduled,
        #[ORM\Column(type: 'integer')]
        private int $attempts = 0,
        #[ORM\Column(type: 'string', length: 300, nullable: true)]
        private ?string $lastError = null,
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $recipientHint = null,
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $providerMessageId = null,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $dispatchedAt = null,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $sentAt = null,
    ) {}

    /**
     * @param array<string, scalar|null> $params
     */
    public static function schedule(
        DeliveryId $id,
        OrganizerId $organizerId,
        ?CampaignId $campaignId,
        string $trigger,
        TemplateKey $templateKey,
        CampaignPurpose $purpose,
        ?EventId $eventId,
        ?OrderId $orderId,
        ?ActivityId $activityId,
        ?string $refId,
        array $params,
        string $idempotencyKey,
        \DateTimeImmutable $sendAfter,
        \DateTimeImmutable $now,
    ): self {
        $delivery = new self($id, $organizerId, $campaignId, $trigger, $templateKey, $purpose, $eventId, $orderId, $activityId, $refId, $params, $idempotencyKey, $sendAfter, $now, $now);
        $delivery->record(new DeliveryChanged($organizerId, $id, $campaignId, 'scheduled', DeliveryState::Scheduled));

        return $delivery;
    }

    public function markSent(WorkflowInterface $machine, ?string $providerMessageId, string $recipientHint, \DateTimeImmutable $now): void
    {
        $this->apply($machine, 'send');
        ++$this->attempts;
        $this->providerMessageId = $providerMessageId;
        $this->recipientHint = $recipientHint;
        $this->lastError = null;
        $this->sentAt = $now;
        $this->updatedAt = $now;
        $this->record(new DeliveryChanged($this->organizerId, $this->id, $this->campaignId, 'sent', $this->state));
    }

    /**
     * COM-02: the condition no longer holds (or the address is suppressed); nothing is sent. `$reason` is a stable code.
     */
    public function skip(WorkflowInterface $machine, string $reason, \DateTimeImmutable $now): void
    {
        $this->apply($machine, 'skip');
        $this->lastError = $reason;
        $this->updatedAt = $now;
        $this->record(new DeliveryChanged($this->organizerId, $this->id, $this->campaignId, 'skipped', $this->state));
    }

    /**
     * One attempt did not go through (the mail service is down). Returns true when no attempt is left.
     */
    public function recordFailedAttempt(string $error, \DateTimeImmutable $now): bool
    {
        ++$this->attempts;
        $this->lastError = $error;
        $this->updatedAt = $now;
        $this->record(new DeliveryChanged($this->organizerId, $this->id, $this->campaignId, 'attempt_failed', $this->state));

        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Gives up (retries exhausted, or the message cannot be composed at all).
     */
    public function fail(WorkflowInterface $machine, string $error, \DateTimeImmutable $now): void
    {
        $this->apply($machine, 'fail');
        $this->lastError = $error;
        $this->updatedAt = $now;
        $this->record(new DeliveryFailedPermanently($this->organizerId, $this->id, $this->campaignId, $this->trigger, $error));
    }

    /**
     * COM-06: "retry the failed ones", same idempotency key, a fresh budget of attempts.
     */
    public function retry(WorkflowInterface $machine, \DateTimeImmutable $now): void
    {
        try {
            $machine->apply($this, 'retry');
        } catch (NotEnabledTransitionException) {
            throw DeliveryNotRetryable::inState($this->state->value);
        }
        $this->attempts = 0;
        $this->lastError = null;
        $this->sendAfter = $now;
        $this->dispatchedAt = $now;
        $this->updatedAt = $now;
        $this->record(new DeliveryRetried($this->organizerId, $this->id, $this->campaignId));
    }

    public function isDispatched(): bool
    {
        return null !== $this->dispatchedAt;
    }

    public function markDispatched(\DateTimeImmutable $now): void
    {
        $this->dispatchedAt = $now;
    }

    /**
     * The moment the message is about to be due changed (an activity was moved): only a delivery that is still waiting.
     */
    public function replan(\DateTimeImmutable $sendAfter, \DateTimeImmutable $now): void
    {
        if (DeliveryState::Scheduled !== $this->state) {
            return;
        }
        $this->sendAfter = $sendAfter;
        $this->dispatchedAt = null;
        $this->updatedAt = $now;
        $this->record(new DeliveryChanged($this->organizerId, $this->id, $this->campaignId, 'replanned', $this->state));
    }

    private function apply(WorkflowInterface $machine, string $transition): void
    {
        $machine->apply($this, $transition);
    }

    public function isDue(\DateTimeImmutable $now): bool
    {
        return DeliveryState::Scheduled === $this->state && $this->sendAfter <= $now;
    }

    public function id(): DeliveryId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function campaignId(): ?CampaignId
    {
        return $this->campaignId;
    }

    public function trigger(): string
    {
        return $this->trigger;
    }

    public function templateKey(): TemplateKey
    {
        return $this->templateKey;
    }

    public function purpose(): CampaignPurpose
    {
        return $this->purpose;
    }

    public function eventId(): ?EventId
    {
        return $this->eventId;
    }

    public function orderId(): ?OrderId
    {
        return $this->orderId;
    }

    public function activityId(): ?ActivityId
    {
        return $this->activityId;
    }

    public function refId(): ?string
    {
        return $this->refId;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function params(): array
    {
        return $this->params;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function sendAfter(): \DateTimeImmutable
    {
        return $this->sendAfter;
    }

    /** Marking store of the `delivery` state machine. */
    public function getState(): DeliveryState
    {
        return $this->state;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setState(DeliveryState $state, array $context = []): void
    {
        $this->state = $state;
    }
}
