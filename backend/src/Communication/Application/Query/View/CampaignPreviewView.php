<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query\View;

/**
 * COM-03: what the message looks like and how many deliveries it would produce right now.
 */
final readonly class CampaignPreviewView
{
    public function __construct(
        public string $subject,
        public string $body,
        public int $recipientCount,
    ) {}
}
