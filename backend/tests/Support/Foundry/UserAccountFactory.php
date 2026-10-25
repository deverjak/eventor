<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Foundry;

use Eventor\Identity\Domain\Account\UserAccount;
use Eventor\Shared\Domain\Id\UserAccountId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<UserAccount>
 */
final class UserAccountFactory extends PersistentObjectFactory
{
    /** A valid hash is irrelevant for tests that never log in; use the owner fixtures (CreateOwner) for login. */
    public const string DUMMY_HASH = '$2y$04$abcdefghijklmnopqrstuuabcdefghijklmnopqrstuvwxyz01234';

    public static function class(): string
    {
        return UserAccount::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array|callable
    {
        return [
            'id' => UserAccountId::generate(),
            'email' => self::faker()->unique()->safeEmail(),
            'passwordHash' => self::DUMMY_HASH,
            'displayName' => self::faker()->name(),
            'now' => new \DateTimeImmutable('2026-10-25T10:00:00+00:00'),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('register'));
    }
}
