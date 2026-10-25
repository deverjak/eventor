<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Registration\Contract\Exception\PersonNotFound;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * Person records for Privacy (PRV-01..04, P1): locating a person and anonymizing them. Anonymization replaces
 * names, birth date, contacts and answers (retention class `registration`); orders, ledger and confirmations stay.
 */
interface PersonDirectory
{
    /**
     * @return list<PersonRecordRef>
     */
    public function search(OrganizerId $organizer, string $term, int $limit = 20): array;

    /**
     * Runs in the caller's transaction. Emits nothing itself: Privacy publishes `privacy.person_anonymized`.
     *
     * @return list<OrderId> orders of the person
     *
     * @throws PersonNotFound
     */
    public function anonymize(OrganizerId $organizer, PersonId $person): array;
}
