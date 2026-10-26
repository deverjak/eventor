<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Correct;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * REG-09: the participant corrects the answers of a person of their order. The new answers replace the old ones as a
 * whole (the form decides what is required); the old set stays as history.
 */
final readonly class CorrectPersonData
{
    /**
     * @param array<string, mixed> $answers raw answers of the person-scoped fields
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public PersonId $personId,
        public array $answers,
    ) {}
}
