<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publication;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of `POST /events/{id}/cancel` (EVT-12 lite): a reason and an explicit confirmation, because cancelling closes
 * all registrations and tells every participant.
 */
final readonly class CancelEventInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public string $reason,
        #[Assert\IsTrue(message: 'Cancelling an event must be confirmed.')]
        public bool $confirmed = false,
    ) {}
}
