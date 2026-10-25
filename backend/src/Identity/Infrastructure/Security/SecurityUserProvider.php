<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Eventor\Identity\Domain\Account\UserAccountRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads team members for the firewall (login and session refresh). A disabled account behaves like an unknown one.
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class SecurityUserProvider implements UserProviderInterface
{
    public function __construct(private UserAccountRepository $accounts) {}

    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        $account = $this->accounts->findByEmail($identifier);
        if (null === $account || $account->isDisabled()) {
            throw new UserNotFoundException();
        }

        return new SecurityUser($account->id()->toString(), $account->email(), $account->passwordHash());
    }

    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(\sprintf('Unsupported user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }
}
