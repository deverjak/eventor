<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Identity\Domain\Account\UserAccount;
use Eventor\Identity\Domain\Account\UserAccountRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(UserAccountRepository::class)]
final readonly class DoctrineUserAccountRepository implements UserAccountRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findByEmail(string $email): ?UserAccount
    {
        return $this->em->getRepository(UserAccount::class)->findOneBy(['email' => UserAccount::normalizeEmail($email)]);
    }

    public function add(UserAccount $account): void
    {
        $this->em->persist($account);
    }
}
