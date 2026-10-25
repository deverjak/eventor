<?php

declare(strict_types=1);

namespace Eventor\Content\Contract;

use Eventor\Content\Contract\Exception\CollectionNotFound;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Galleries, external collections and materials as the public page and the participants' area show them (POST-01..07).
 * URLs are time-limited and signed; nothing here contains personal data.
 */
interface PublicContentReader
{
    /**
     * Collections visible to the audience (public for anonymous visitors, participants for self-service).
     *
     * @return list<CollectionView>
     */
    public function collectionsFor(OrganizerId $organizer, EventId $event, CollectionVisibility $audience): array;

    /**
     * @return list<PhotoView> ordered by position
     *
     * @throws CollectionNotFound when the collection does not exist or is not visible to the audience
     */
    public function photos(OrganizerId $organizer, CollectionId $collection, CollectionVisibility $audience, int $limit = 60, int $offset = 0): array;

    /**
     * @return list<MaterialView>
     */
    public function materials(OrganizerId $organizer, EventId $event): array;
}
