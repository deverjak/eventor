<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Membership;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * One row of `membership_event_scope`: the membership is restricted to this event. No rows = all events of the organizer.
 */
#[ORM\Entity]
#[ORM\Table(name: 'membership_event_scope', schema: 'identity')]
class MembershipEventScope implements TenantScoped
{
    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: Membership::class, inversedBy: 'scope')]
        #[ORM\JoinColumn(name: 'membership_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Membership $membership,
        #[ORM\Id]
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
    ) {
        $this->membershipId = $membership->id();
    }

    /** Read-only mirror of the join column (the association is the identifier), so the column is visible as a field. */
    #[ORM\Column(name: 'membership_id', type: 'membership_id', insertable: false, updatable: false)]
    private MembershipId $membershipId;

    public function membershipId(): MembershipId
    {
        return $this->membershipId;
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }
}
