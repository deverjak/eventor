<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Answers;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * `catalog.form_version_published` (S17): a new required field came after registrations. Marks the participations
 * without an answer `answers_state = incomplete` and announces them per order, so Communication asks the orderers.
 */
final readonly class RequestMissingAnswers
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public FormVersionId $formVersionId,
    ) {}
}
