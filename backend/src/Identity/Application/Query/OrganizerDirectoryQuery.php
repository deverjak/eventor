<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Contract\OrganizerDirectory;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * DBAL read model behind `OrganizerDirectory`: one query joins organizer, membership and account, so an unknown
 * slug and a foreign slug produce the same empty result.
 */
#[AsAlias(OrganizerDirectory::class)]
final readonly class OrganizerDirectoryQuery implements OrganizerDirectory
{
    public function __construct(private Connection $connection) {}

    public function findForMember(string $slug, string $userIdentifier): ?OrganizerView
    {
        /** @var array{id: string, slug: string, name: string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT o.id, o.slug, o.name
               FROM identity.organizer o
               JOIN identity.membership m ON m.organizer_id = o.id
               JOIN identity.user_account u ON u.id = m.user_account_id
              WHERE o.slug = :slug AND u.email = :email AND u.disabled_at IS NULL',
            ['slug' => $slug, 'email' => mb_strtolower($userIdentifier)],
        );

        return false === $row ? null : new OrganizerView(OrganizerId::fromString($row['id']), $row['slug'], $row['name']);
    }
}
