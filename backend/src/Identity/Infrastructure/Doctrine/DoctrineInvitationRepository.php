<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Identity\Domain\Invitation\Invitation;
use Eventor\Identity\Domain\Invitation\InvitationRepository;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(InvitationRepository::class)]
final readonly class DoctrineInvitationRepository implements InvitationRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(Invitation $invitation): void
    {
        $this->em->persist($invitation);
    }

    public function find(InvitationId $id): ?Invitation
    {
        return $this->em->getRepository(Invitation::class)->findOneBy(['id' => $id]);
    }

    public function findPending(OrganizerId $organizer, string $email, \DateTimeImmutable $now): array
    {
        $found = $this->em->getRepository(Invitation::class)->findBy(['organizerId' => $organizer, 'email' => mb_strtolower(trim($email))]);

        return array_values(array_filter($found, static fn(Invitation $i): bool => $i->isPending($now)));
    }
}
