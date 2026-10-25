<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Foundry;

use Eventor\Identity\Domain\Membership\Membership;
use Eventor\Identity\Domain\Membership\MembershipId;
use Eventor\Identity\Domain\Membership\Role;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Pass `userAccountId` and `organizerId` explicitly (modules reference each other by id, not by association):
 *
 *     MembershipFactory::createOne(['userAccountId' => $account->id(), 'organizerId' => $organizer->id(), 'role' => Role::Finance]);
 *
 * @extends PersistentObjectFactory<Membership>
 */
final class MembershipFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Membership::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array|callable
    {
        return [
            'id' => MembershipId::generate(),
            'role' => Role::Owner,
            'now' => new \DateTimeImmutable('2026-10-25T10:00:00+00:00'),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('grant'));
    }
}
