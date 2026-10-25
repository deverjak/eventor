<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Identity\Domain\Membership\Membership;
use Eventor\Identity\Domain\Membership\MembershipRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(MembershipRepository::class)]
final readonly class DoctrineMembershipRepository implements MembershipRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(Membership $membership): void
    {
        $this->em->persist($membership);
    }
}
