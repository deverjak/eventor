<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Content;

use Eventor\Content\Contract\CollectionVisibility;
use Eventor\Content\Contract\MaterialType;
use Eventor\Content\Domain\Collection\Collection;
use Eventor\Content\Domain\Collection\Photo;
use Eventor\Content\Domain\Event\CollectionChanged;
use Eventor\Content\Domain\Event\PhotoAdded;
use Eventor\Content\Domain\Exception\CollectionIsExternal;
use Eventor\Content\Domain\Material\Material;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\MaterialId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PhotoId;
use PHPUnit\Framework\TestCase;

final class CollectionTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-23 10:00:00+01:00');
    }

    public function test_only_a_link_only_collection_gets_an_unguessable_token(): void
    {
        self::assertNull($this->collection(CollectionVisibility::Public)->linkToken());
        $token = $this->collection(CollectionVisibility::LinkOnly)->linkToken();
        self::assertIsString($token);
        self::assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);
        self::assertNotSame($token, $this->collection(CollectionVisibility::LinkOnly)->linkToken());
    }

    public function test_publishing_twice_records_one_event_and_unpublishing_records_another(): void
    {
        $collection = $this->collection(CollectionVisibility::Public);
        $collection->releaseEvents();

        $collection->publish($this->now);
        $collection->publish($this->now);
        $collection->unpublish();
        $collection->unpublish();
        $collection->designateForArchive(true);
        $collection->designateForArchive(true);

        $actions = array_map(static fn(object $event): string => $event instanceof CollectionChanged ? $event->action : $event::class, $collection->releaseEvents());
        self::assertSame(['published', 'unpublished', 'archived'], $actions);
        self::assertFalse($collection->isPublished());
    }

    public function test_an_external_collection_takes_no_photos(): void
    {
        $external = Collection::create(CollectionId::generate(), OrganizerId::generate(), EventId::generate(), 'Fotograf', CollectionVisibility::Public, 'https://galerie.example.test', $this->now);

        $this->expectException(CollectionIsExternal::class);
        Photo::add(PhotoId::generate(), $external, 'k.jpg', 'image/jpeg', null, null, null, 0, $this->now);
    }

    public function test_a_photo_of_a_gallery_announces_itself_for_the_thumbnail(): void
    {
        $gallery = $this->collection(CollectionVisibility::Public);
        $photo = Photo::add(PhotoId::generate(), $gallery, 'org/col/p.jpg', 'image/jpeg', 'Start', 'Foto', null, 3, $this->now);

        $events = $photo->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PhotoAdded::class, $events[0]);
        self::assertSame('org/col/p.jpg', $events[0]->storageKey);
        self::assertNull($photo->thumbnailKey());
        $photo->attachThumbnail('org/col/p-thumb.jpg');
        self::assertSame('org/col/p-thumb.jpg', $photo->thumbnailKey());
    }

    public function test_the_audit_snapshot_keeps_the_link_token_secret(): void
    {
        $collection = $this->collection(CollectionVisibility::LinkOnly);
        $event = $collection->releaseEvents()[0];
        self::assertInstanceOf(CollectionChanged::class, $event);

        self::assertArrayNotHasKey('linkToken', $event->after);
        self::assertNotContains($collection->linkToken(), $event->after);
    }

    public function test_a_material_follows_the_same_publication_rules(): void
    {
        $material = Material::add(MaterialId::generate(), OrganizerId::generate(), EventId::generate(), ' Výsledky ', 'https://vysledky.example.test', MaterialType::Results, 0, $this->now);
        $material->publish($this->now);
        $material->publish($this->now);
        $material->designateForArchive(true);

        self::assertCount(3, $material->releaseEvents(), 'added, published, archived');
    }

    private function collection(CollectionVisibility $visibility): Collection
    {
        return Collection::create(CollectionId::generate(), OrganizerId::generate(), EventId::generate(), 'Galerie', $visibility, null, $this->now);
    }
}
