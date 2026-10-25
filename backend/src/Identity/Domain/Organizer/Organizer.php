<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Organizer;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Identity\Domain\Event\OrganizerRegistered;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The tenant: an organization that runs events. Global (not tenant-scoped itself): its id is the tenant key of
 * every other tenant-scoped table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'organizer', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'organizer_slug_key', columns: ['slug'])]
class Organizer implements RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'string', length: 64)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 200)]
    private string $name;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $id,
        string $slug,
        string $name,
        \DateTimeImmutable $createdAt,
    ) {
        $this->slug = $slug;
        $this->name = $name;
        $this->createdAt = $createdAt;
    }

    public static function register(OrganizerId $id, string $slug, string $name, \DateTimeImmutable $now): self
    {
        $organizer = new self($id, $slug, $name, $now);
        $organizer->record(new OrganizerRegistered($id, $slug, $name));

        return $organizer;
    }

    public function id(): OrganizerId
    {
        return $this->id;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
