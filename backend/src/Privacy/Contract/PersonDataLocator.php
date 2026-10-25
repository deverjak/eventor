<?php

declare(strict_types=1);

namespace Eventor\Privacy\Contract;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Finds the records of one person across modules (PRV-01, P1). Privacy asks each owning module through its own
 * contract (Registration: `PersonDirectory`) and merges the results; export and anonymization follow from the match.
 */
interface PersonDataLocator
{
    /**
     * @return list<PersonMatch>
     */
    public function find(OrganizerId $organizer, PersonSearch $search): array;
}
