<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Sending;

use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * A fully rendered message ready for a channel. Personal data: lives for the duration of one send, never persisted.
 */
final readonly class OutgoingMessage
{
    /**
     * @param list<PersonId> $consentPersons marketing only: the people whose consent entitles us to write
     */
    public function __construct(
        public DeliveryId $deliveryId,
        public OrganizerId $organizerId,
        public CampaignPurpose $purpose,
        public string $fromName,
        public string $replyToEmail,
        public string $toEmail,
        public string $toName,
        public string $subject,
        public string $text,
        public ?string $qrPng,
        public array $consentPersons,
    ) {}
}
