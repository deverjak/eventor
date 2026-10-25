<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\Identity\Domain\Membership\Membership;
use Eventor\Identity\Domain\Membership\MembershipRepository;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(MembershipRepository::class)]
final readonly class DoctrineMembershipRepository implements MembershipRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
    ) {}

    public function add(Membership $membership): void
    {
        $this->em->persist($membership);
    }

    public function find(MembershipId $id, OrganizerId $organizer): ?Membership
    {
        return $this->em->getRepository(Membership::class)->findOneBy(['id' => $id, 'organizerId' => $organizer]);
    }

    public function remove(Membership $membership): void
    {
        // A plain DELETE (the scope rows go with it, ON DELETE CASCADE) instead of EntityManager::remove(): a removed
        // entity leaves the identity map at flush time, which would drop its recorded MembershipRevoked event
        // (DomainEventCollector reads the identity map). The stale managed copy is never touched again.
        $this->connection->executeStatement(
            'DELETE FROM identity.membership WHERE id = :id AND organizer_id = :organizer',
            ['id' => $membership->id()->toString(), 'organizer' => $membership->organizerId()->toString()],
        );
    }

    public function exists(UserAccountId $account, OrganizerId $organizer): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM identity.membership WHERE user_account_id = :account AND organizer_id = :organizer',
            ['account' => $account->toString(), 'organizer' => $organizer->toString()],
        );
    }

    public function countOwners(OrganizerId $organizer): int
    {
        $count = $this->connection->fetchOne(
            "SELECT count(*) FROM identity.membership WHERE organizer_id = :organizer AND role = 'OWNER'",
            ['organizer' => $organizer->toString()],
        );

        return is_numeric($count) ? (int) $count : 0;
    }
}
