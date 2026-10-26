<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Participation;

use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * Participations of the active tenant (the Doctrine `organizer` filter scopes every lookup).
 */
interface ParticipationRepository
{
    /**
     * @throws ParticipationNotFound
     */
    public function get(ParticipationId $id): Participation;

    /**
     * @return list<Participation> in creation order
     */
    public function ofOrder(OrderId $order): array;

    /**
     * Pending, confirmed and flagged participations of an event (the ones that still count).
     *
     * @return list<Participation>
     */
    public function activeOfEvent(EventId $event): array;

    /**
     * @return list<Participation> every participation of a person, in creation order
     */
    public function ofPerson(PersonId $person): array;

    public function add(Participation $participation): void;
}
