<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Contract\Exception\OrganizerNotFound;
use Eventor\Identity\Contract\OrganizerLookup;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Organizer lookups without a signed-in member (public zone, subscribers, jobs).
 */
#[AsAlias(OrganizerLookup::class)]
final readonly class OrganizerLookupQuery implements OrganizerLookup
{
    public function __construct(private Connection $connection) {}

    public function findBySlug(string $slug): ?OrganizerView
    {
        /** @var array{id: string, slug: string, name: string}|false $row */
        $row = $this->connection->fetchAssociative('SELECT id, slug, name FROM identity.organizer WHERE slug = :slug', ['slug' => $slug]);

        return false === $row ? null : new OrganizerView(OrganizerId::fromString($row['id']), $row['slug'], $row['name']);
    }

    public function get(OrganizerId $id): OrganizerView
    {
        /** @var array{id: string, slug: string, name: string}|false $row */
        $row = $this->connection->fetchAssociative('SELECT id, slug, name FROM identity.organizer WHERE id = :id', ['id' => $id->toString()]);
        if (false === $row) {
            throw OrganizerNotFound::forId($id);
        }

        return new OrganizerView(OrganizerId::fromString($row['id']), $row['slug'], $row['name']);
    }
}
