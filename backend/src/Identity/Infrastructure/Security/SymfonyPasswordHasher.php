<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Eventor\Identity\Application\Port\PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Hashes with the algorithm configured for SecurityUser in security.php (`auto`: Argon2id), so stored hashes are
 * always verifiable by the firewall.
 */
final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private PasswordHasherFactoryInterface $factory) {}

    public function hash(#[\SensitiveParameter] string $plainPassword): string
    {
        return $this->factory->getPasswordHasher(SecurityUser::class)->hash($plainPassword);
    }
}
