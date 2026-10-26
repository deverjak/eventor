<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Campaign;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\CampaignState;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Event\CampaignChanged;
use Eventor\Communication\Domain\Event\CampaignCompleted;
use Eventor\Communication\Domain\Exception\CampaignStateInvalid;
use Eventor\Communication\Domain\Exception\InvalidCampaign;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * A message to many people (COM-03): the wording, the audience and the moment. Lifecycle (state machine `campaign`,
 * declared in the module's services.php): draft -> scheduled -> sending -> sent, cancellable until it starts. The
 * state setter is the workflow's marking store: call the methods below, they apply the transition.
 */
#[ORM\Entity]
#[ORM\Table(name: 'campaign', schema: 'communication')]
#[ORM\Index(name: 'campaign_organizer_event_idx', columns: ['organizer_id', 'event_id', 'created_at'])]
class Campaign implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    public const int MAX_NAME = 200;

    /**
     * @param array<string, mixed> $audience
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'campaign_id')]
        private CampaignId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        #[ORM\Column(type: 'string', length: 200)]
        private string $name,
        #[ORM\Column(type: 'string', length: 16, enumType: CampaignPurpose::class)]
        private CampaignPurpose $purpose,
        #[ORM\Column(name: 'template_key', type: 'string', length: 64, nullable: true, enumType: TemplateKey::class)]
        private ?TemplateKey $templateKey,
        #[ORM\Column(type: 'string', length: 5, enumType: MessageLocale::class)]
        private MessageLocale $locale,
        #[ORM\Column(type: 'string', length: 300)]
        private string $subject,
        #[ORM\Column(type: 'text')]
        private string $body,
        #[ORM\Column(type: 'json', options: ['jsonb' => true])]
        private array $audience,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $updatedAt,
        #[ORM\Column(type: 'string', length: 16, enumType: CampaignState::class)]
        private CampaignState $state = CampaignState::Draft,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $scheduledAt = null,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $startedAt = null,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $completedAt = null,
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $proposalKey = null,
        #[ORM\Column(type: 'string', length: 64, nullable: true)]
        private ?string $refId = null,
    ) {}

    public function refId(): ?string
    {
        return $this->refId;
    }

    public static function draft(
        CampaignId $id,
        OrganizerId $organizerId,
        EventId $eventId,
        string $name,
        CampaignPurpose $purpose,
        ?TemplateKey $templateKey,
        MessageLocale $locale,
        string $subject,
        string $body,
        CampaignAudienceSpec $audience,
        \DateTimeImmutable $now,
        ?string $proposalKey = null,
        ?string $refId = null,
    ): self {
        self::guard($name, $subject, $body);
        $campaign = new self($id, $organizerId, $eventId, trim($name), $purpose, $templateKey, $locale, trim($subject), $body, $audience->toArray(), $now, $now, proposalKey: $proposalKey, refId: $refId);
        $campaign->record(new CampaignChanged($organizerId, $id, 'drafted', CampaignState::Draft));

        return $campaign;
    }

    public function edit(string $name, CampaignPurpose $purpose, ?TemplateKey $templateKey, MessageLocale $locale, string $subject, string $body, CampaignAudienceSpec $audience, \DateTimeImmutable $now): void
    {
        if (CampaignState::Draft !== $this->state) {
            throw CampaignStateInvalid::for('edited', $this->state->value);
        }
        self::guard($name, $subject, $body);
        $this->name = trim($name);
        $this->purpose = $purpose;
        $this->templateKey = $templateKey;
        $this->locale = $locale;
        $this->subject = trim($subject);
        $this->body = $body;
        $this->audience = $audience->toArray();
        $this->updatedAt = $now;
        $this->record(new CampaignChanged($this->organizerId, $this->id, 'edited', $this->state));
    }

    public function schedule(WorkflowInterface $machine, \DateTimeImmutable $at, \DateTimeImmutable $now): void
    {
        if ($at <= $now) {
            throw InvalidCampaign::because('A campaign can only be scheduled in the future; send it now instead.');
        }
        $this->apply($machine, 'schedule');
        $this->scheduledAt = $at;
        $this->updatedAt = $now;
        $this->record(new CampaignChanged($this->organizerId, $this->id, 'scheduled', $this->state));
    }

    public function start(WorkflowInterface $machine, \DateTimeImmutable $now): void
    {
        $this->apply($machine, 'start');
        $this->startedAt = $now;
        $this->updatedAt = $now;
        $this->record(new CampaignChanged($this->organizerId, $this->id, 'started', $this->state));
    }

    public function complete(WorkflowInterface $machine, int $sent, int $skipped, \DateTimeImmutable $now): void
    {
        $this->apply($machine, 'complete');
        $this->completedAt = $now;
        $this->updatedAt = $now;
        $this->record(new CampaignCompleted($this->organizerId, $this->id, $sent, $skipped));
    }

    public function cancel(WorkflowInterface $machine, \DateTimeImmutable $now): void
    {
        $this->apply($machine, 'cancel');
        $this->updatedAt = $now;
        $this->record(new CampaignChanged($this->organizerId, $this->id, 'cancelled', $this->state));
    }

    private function apply(WorkflowInterface $machine, string $transition): void
    {
        try {
            $machine->apply($this, $transition);
        } catch (NotEnabledTransitionException) {
            throw CampaignStateInvalid::for($transition, $this->state->value);
        }
    }

    private static function guard(string $name, string $subject, string $body): void
    {
        if ('' === trim($name) || mb_strlen($name) > self::MAX_NAME) {
            throw InvalidCampaign::because(\sprintf('A campaign needs a name of at most %d characters.', self::MAX_NAME));
        }
        if ('' === trim($subject) || '' === trim($body)) {
            throw InvalidCampaign::because('A campaign needs a subject and a body.');
        }
    }

    public function id(): CampaignId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function purpose(): CampaignPurpose
    {
        return $this->purpose;
    }

    public function templateKey(): ?TemplateKey
    {
        return $this->templateKey;
    }

    public function locale(): MessageLocale
    {
        return $this->locale;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function audience(): CampaignAudienceSpec
    {
        return CampaignAudienceSpec::fromArray($this->audience);
    }

    public function scheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    /** Marking store of the `campaign` state machine. */
    public function getState(): CampaignState
    {
        return $this->state;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setState(CampaignState $state, array $context = []): void
    {
        $this->state = $state;
    }
}
