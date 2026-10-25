<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Eventor\Shared\Application\Security\IdentifiableUser;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The authenticated principal of a team member. Identified by e-mail for Symfony (login name), by account id for
 * audit. The session stores only a fingerprint of the password hash: changing the password invalidates old sessions.
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, IdentifiableUser, EquatableInterface
{
    private readonly string $passwordFingerprint;

    /**
     * @param non-empty-string $email
     */
    public function __construct(
        private readonly string $accountId,
        private readonly string $email,
        private readonly string $passwordHash,
    ) {
        $this->passwordFingerprint = hash('crc32c', $passwordHash);
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->accountId === $this->accountId
            && $user->email === $this->email
            && $user->passwordFingerprint === $this->passwordFingerprint;
    }

    /**
     * @return array<string, string>
     */
    public function __serialize(): array
    {
        return [
            'accountId' => $this->accountId,
            'email' => $this->email,
            'passwordFingerprint' => $this->passwordFingerprint,
        ];
    }

    /**
     * @param array<string, string> $data
     */
    public function __unserialize(array $data): void
    {
        $this->accountId = $data['accountId'];
        if ('' === $data['email']) {
            throw new \UnexpectedValueException('Corrupt session data.');
        }
        $this->email = $data['email'];
        $this->passwordFingerprint = $data['passwordFingerprint'];
        $this->passwordHash = '';
    }
}
