<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Scheduling;

use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\TemplateKey;

/**
 * Plans one message to one recipient (chapter 06, "Odesílání e-mailů a idempotence"): the subscribers of integration
 * events dispatch it. The idempotency key (`<fact>:<id>[:<detail>]`) makes a redelivered event harmless. The references
 * (`orderId`, `activityId`, `refId`, `params`) are what the text is rendered from at send time; no address travels here.
 */
final readonly class ScheduleDelivery
{
    /**
     * @param array<string, scalar|null> $params neutral values the template needs
     */
    public function __construct(
        public TemplateKey $trigger,
        public string $idempotencyKey,
        public ?string $eventId = null,
        public ?string $orderId = null,
        public ?string $activityId = null,
        public ?string $refId = null,
        public array $params = [],
        public ?\DateTimeImmutable $sendAfter = null,
        public CampaignPurpose $purpose = CampaignPurpose::Organizational,
    ) {}
}
