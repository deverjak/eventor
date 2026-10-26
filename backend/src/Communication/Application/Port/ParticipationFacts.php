<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Port;

use Eventor\Registration\Contract\ParticipationView;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * The facts of participations Communication needs (the order and the person of a participation). Registration's
 * `ParticipationQuery` stands behind it; while Registration does not provide one, the port answers with nothing.
 */
interface ParticipationFacts
{
    /**
     * @param list<ParticipationId> $ids
     *
     * @return list<ParticipationView> unknown ids are skipped
     */
    public function find(OrganizerId $organizer, array $ids): array;
}
