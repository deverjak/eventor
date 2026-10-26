<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Suppression;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Communication\Contract\SuppressionScope;
use Eventor\Communication\Domain\Event\SuppressionChanged;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * An address that must not receive messages of a scope (PRV-07, COM-09): stored as a hash, never in clear.
 */
#[ORM\Entity]
#[ORM\Table(name: 'suppression', schema: 'communication')]
#[ORM\UniqueConstraint(name: 'suppression_unique', columns: ['organizer_id', 'email_sha256', 'scope'])]
class Suppression implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'suppression_id')]
        private SuppressionId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(name: 'email_sha256', type: 'string', length: 64, options: ['fixed' => true])]
        private string $emailSha256,
        #[ORM\Column(type: 'string', length: 16, enumType: SuppressionScope::class)]
        private SuppressionScope $scope,
        #[ORM\Column(type: 'string', length: 32)]
        private string $reason,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function add(SuppressionId $id, OrganizerId $organizerId, string $emailSha256, SuppressionScope $scope, string $reason, \DateTimeImmutable $now): self
    {
        $suppression = new self($id, $organizerId, $emailSha256, $scope, $reason, $now);
        $suppression->record(new SuppressionChanged($organizerId, $id, $scope, 'added'));

        return $suppression;
    }

    public function lift(): void
    {
        $this->record(new SuppressionChanged($this->organizerId, $this->id, $this->scope, 'lifted'));
    }

    public function id(): SuppressionId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function scope(): SuppressionScope
    {
        return $this->scope;
    }
}
