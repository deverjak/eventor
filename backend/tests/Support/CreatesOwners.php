<?php

declare(strict_types=1);

namespace Eventor\Tests\Support;

use Eventor\Identity\Application\CreateOwner\CreateOwner;
use Eventor\Shared\Application\Messaging\CommandBus;

/**
 * Test fixture: an organizer with its OWNER, created by the real `CreateOwner` command (password hashing, audit,
 * domain and integration events included).
 */
trait CreatesOwners
{
    abstract protected function commandBus(): CommandBus;

    /**
     * @return array{organizerId: string, userAccountId: string, membershipId: string}
     */
    protected function createOwner(string $email, string $slug, ?string $organizerName = null, string $password = 'correct-horse-battery'): array
    {
        /** @var array{organizerId: string, userAccountId: string, membershipId: string} $result */
        $result = $this->commandBus()->dispatch(new CreateOwner(
            $email,
            $password,
            explode('@', $email)[0],
            $organizerName ?? ucfirst($slug),
            $slug,
        ));

        return $result;
    }
}
