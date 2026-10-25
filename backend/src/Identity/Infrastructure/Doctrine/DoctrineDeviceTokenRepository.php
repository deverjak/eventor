<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Identity\Domain\DeviceToken\DeviceToken;
use Eventor\Identity\Domain\DeviceToken\DeviceTokenId;
use Eventor\Identity\Domain\DeviceToken\DeviceTokenRepository;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(DeviceTokenRepository::class)]
final readonly class DoctrineDeviceTokenRepository implements DeviceTokenRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(DeviceToken $token): void
    {
        $this->em->persist($token);
    }

    public function find(DeviceTokenId $id, OrganizerId $organizer): ?DeviceToken
    {
        return $this->em->getRepository(DeviceToken::class)->findOneBy(['id' => $id, 'organizerId' => $organizer]);
    }
}
