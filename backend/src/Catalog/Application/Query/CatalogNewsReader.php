<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Contract\Exception\NewsPostNotFound;
use Eventor\Catalog\Contract\NewsPostView;
use Eventor\Catalog\Contract\NewsReader;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\NewsPostId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Content of a published news post, for the e-mail broadcast (PRG-04). A draft is "not found".
 */
#[AsAlias(NewsReader::class)]
final readonly class CatalogNewsReader implements NewsReader
{
    public function __construct(private Connection $connection) {}

    public function post(OrganizerId $organizer, NewsPostId $post): NewsPostView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, event_id, title, body, published_at FROM catalog.news_post WHERE organizer_id = :organizer AND id = :id AND published_at IS NOT NULL',
            ['organizer' => $organizer->toString(), 'id' => $post->toString()],
        );
        if (false === $row) {
            throw NewsPostNotFound::forId($post);
        }

        return new NewsPostView(
            NewsPostId::fromString(Hydrate::string($row, 'id')),
            EventId::fromString(Hydrate::string($row, 'event_id')),
            Hydrate::string($row, 'title'),
            Hydrate::string($row, 'body'),
            Hydrate::time($row, 'published_at'),
        );
    }
}
