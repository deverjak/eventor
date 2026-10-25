<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Foundry;

use Eventor\Identity\Domain\Organizer\Organizer;
use Eventor\Shared\Domain\Id\OrganizerId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Organizer>
 */
final class OrganizerFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Organizer::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array|callable
    {
        return [
            'id' => OrganizerId::generate(),
            'slug' => 'org-'.bin2hex(random_bytes(4)),
            'name' => self::faker()->company(),
            'now' => new \DateTimeImmutable('2026-10-25T10:00:00+00:00'),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('register'));
    }
}
