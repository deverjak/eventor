<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Identity\Domain\Organizer\OrganizerPolicy;
use Eventor\Identity\Domain\Organizer\OrganizerPolicyRepository;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(OrganizerPolicyRepository::class)]
final readonly class DoctrineOrganizerPolicyRepository implements OrganizerPolicyRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(OrganizerPolicy $policy): void
    {
        $this->em->persist($policy);
    }

    public function getOrCreate(OrganizerId $organizer): OrganizerPolicy
    {
        $policy = $this->em->getRepository(OrganizerPolicy::class)->findOneBy(['organizerId' => $organizer]);
        if (null === $policy) {
            $policy = OrganizerPolicy::defaultsFor($organizer);
            $this->em->persist($policy);
        }

        return $policy;
    }
}
