<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Account;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Shared\Domain\Id\UserAccountId;

/**
 * A person who can sign in. One account can be a member of several organizers (memberships carry the role).
 * The e-mail is the login and is stored lower-case; the password is only ever held as a hash.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_account', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'user_account_email_key', columns: ['email'])]
class UserAccount
{
    /** @var non-empty-string */
    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'string', length: 255)]
    private string $passwordHash;

    #[ORM\Column(type: 'string', length: 200)]
    private string $displayName;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $disabledAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'user_account_id')]
        private UserAccountId $id,
        string $email,
        string $passwordHash,
        string $displayName,
        \DateTimeImmutable $createdAt,
    ) {
        $this->email = self::normalizeEmail($email);
        $this->passwordHash = $passwordHash;
        $this->displayName = $displayName;
        $this->createdAt = $createdAt;
    }

    public static function register(UserAccountId $id, string $email, string $passwordHash, string $displayName, \DateTimeImmutable $now): self
    {
        return new self($id, $email, $passwordHash, $displayName, $now);
    }

    /**
     * @return non-empty-string
     */
    public static function normalizeEmail(string $email): string
    {
        $normalized = mb_strtolower(trim($email));
        if ('' === $normalized) {
            throw new \InvalidArgumentException('An account needs an e-mail address.');
        }

        return $normalized;
    }

    public function id(): UserAccountId
    {
        return $this->id;
    }

    /**
     * @return non-empty-string
     */
    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isDisabled(): bool
    {
        return null !== $this->disabledAt;
    }
}
