<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Content;

use Eventor\Content\Application\Port\PhotoStorage;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\Content\ContentHttpTestCase;

/**
 * POST-01..03 over HTTP: galleries, uploads with asynchronous thumbnails, external collections, publication and the
 * unguessable link; tenancy and roles.
 */
final class CollectionEndpointsTest extends ContentHttpTestCase
{
    public function test_a_gallery_is_uploaded_into_gets_thumbnails_and_is_public_once_published(): void
    {
        $collection = $this->createCollection();
        self::assertNull($collection['linkToken']);

        $photo = $this->upload($collection['collectionId'], fields: ['description' => 'Start', 'author' => 'Foto Plzeň', 'eventPart' => 'Sobota']);
        self::assertResponseStatusCodeSame(201);
        $photoId = Json::str($photo, 'photoId');

        $photos = $this->request('GET', $this->admin('/collections/'.$collection['collectionId'].'/photos'));
        self::assertSame([[
            'id' => $photoId, 'description' => 'Start', 'author' => 'Foto Plzeň', 'eventPart' => 'Sobota', 'position' => 0, 'hasThumbnail' => true,
        ]], Json::arr($photos, 'items'));
        $thumbnail = $this->dbString('SELECT thumbnail_key FROM content.photo WHERE id = ?', [$photoId]);
        $storage = static::getContainer()->get('test.'.PhotoStorage::class);
        \assert($storage instanceof PhotoStorage);
        $size = getimagesizefromstring($storage->read($thumbnail));
        self::assertNotFalse($size);
        self::assertSame(480, max($size[0], $size[1]), 'the thumbnail fits into 480 px');

        self::assertSame([], Json::arr($this->request('GET', $this->public('/content')), 'collections'), 'a draft is not public');

        $this->request('POST', $this->admin('/collections/'.$collection['collectionId'].'/publish'));
        self::assertResponseIsSuccessful();
        $this->drain();

        $page = $this->request('GET', $this->public('/content'));
        self::assertSame('Fotky ze závodu', Json::str($page, 'collections.0.title'));
        self::assertSame(1, Json::int($page, 'collections.0.photoCount'));
        self::assertSame('/api/public/content/photos/'.$photoId.'/thumbnail', Json::str($page, 'collections.0.coverUrl'));
        $public = $this->request('GET', $this->public('/collections/'.$collection['collectionId'].'/photos'));
        self::assertSame('Foto Plzeň', Json::str($public, 'items.0.author'));

        $this->anonymous();
        $this->client->request('GET', '/api/public/content/photos/'.$photoId.'/thumbnail');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
        $this->client->request('GET', '/api/public/content/photos/'.$photoId);
        self::assertResponseIsSuccessful();
    }

    public function test_publishing_emits_the_integration_event_and_unpublishing_hides_the_collection_again(): void
    {
        $collection = $this->createCollection();
        $this->request('POST', $this->admin('/collections/'.$collection['collectionId'].'/publish'));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'content.collection_published' AND entity_id = ?", [$collection['collectionId']]));
        $this->drain();

        $this->request('POST', $this->admin('/collections/'.$collection['collectionId'].'/unpublish'));
        self::assertResponseIsSuccessful();
        self::assertFalse(Json::at($this->request('GET', $this->admin('/collections')), 'items.0.published'));
        self::assertSame([], Json::arr($this->request('GET', $this->public('/content')), 'collections'));
    }

    public function test_a_link_only_collection_opens_only_with_its_token(): void
    {
        $collection = $this->createCollection('Jen pro rodiče', 'link_only');
        $token = $collection['linkToken'];
        self::assertIsString($token);
        self::assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);
        $photoId = Json::str($this->upload($collection['collectionId']), 'photoId');
        $this->request('POST', $this->admin('/collections/'.$collection['collectionId'].'/publish'));
        $this->drain();
        $this->anonymous();

        self::assertSame([], Json::arr($this->request('GET', $this->public('/content')), 'collections'), 'not on the public page');
        $this->request('GET', $this->public('/collections/'.$collection['collectionId'].'/photos'));
        $this->assertProblem(404, 'collection_not_found');

        $opened = $this->request('GET', $this->public('/collection-links/'.$token));
        self::assertSame($collection['collectionId'], Json::str($opened, 'collectionId'));
        self::assertSame('/api/public/content/photos/'.$photoId.'?token='.$token, Json::str($opened, 'items.0.url'));
        $this->request('GET', $this->public('/collection-links/'.str_repeat('0', 48)));
        $this->assertProblem(404, 'collection_not_found');

        $this->request('GET', '/api/public/content/photos/'.$photoId);
        $this->assertProblem(404, 'photo_not_found');
        $this->client->request('GET', '/api/public/content/photos/'.$photoId.'?token='.$token);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('private', (string) $this->client->getResponse()->headers->get('Cache-Control'), 'a link-only photo stays out of shared caches');
    }

    public function test_an_external_collection_is_a_link_and_takes_no_photos(): void
    {
        $collection = $this->createCollection('Fotograf', 'public', 'https://galerie.example.test/borsky-2026');
        $this->upload($collection['collectionId']);
        $this->assertProblem(409, 'collection_is_external');
        $this->request('POST', $this->admin('/collections/'.$collection['collectionId'].'/publish'));
        $this->drain();

        self::assertSame('https://galerie.example.test/borsky-2026', Json::str($this->request('GET', $this->public('/content')), 'collections.0.externalUrl'));
    }

    public function test_bodies_and_files_are_validated(): void
    {
        $this->request('POST', $this->admin('/collections'), ['title' => '']);
        $this->assertProblem(422, 'validation_failed');
        $this->request('POST', $this->admin('/collections'), ['title' => 'X', 'visibility' => 'participants']);
        $this->assertProblem(422, 'validation_failed');
        $this->request('POST', $this->admin('/collections'), ['title' => 'X', 'externalUrl' => 'http://insecure.example.test/x']);
        $this->assertProblem(422, 'validation_failed');

        $collection = $this->createCollection();
        $this->upload($collection['collectionId'], 'not an image at all');
        $this->assertProblem(422, 'invalid_photo');
        $this->request('POST', $this->admin('/collections/'.$collection['collectionId'].'/photos'), []);
        $this->assertProblem(422, 'invalid_photo');
        $this->request('PUT', $this->admin('/collections/'.$collection['collectionId'].'/archive'), []);
        $this->assertProblem(422, 'validation_failed');
    }

    public function test_content_belongs_to_a_published_event_of_the_organizer(): void
    {
        $draft = $this->createEvent(slug: 'koncept');
        $this->request('POST', $this->admin('/collections', Json::str($draft, 'eventId')), ['title' => 'Galerie']);
        $this->assertProblem(404, 'event_not_found');

        $this->request('POST', $this->admin('/collections', orgSlug: 'cizi-klub'), ['title' => 'Galerie']);
        $this->assertProblem(404, 'not_found');

        $mine = $this->createCollection();
        $other = $this->createPublishableEvent('jine');
        $this->publish($other['eventId']);
        $this->drain();
        $this->request('POST', $this->admin('/collections/'.$mine['collectionId'].'/publish', $other['eventId']));
        $this->assertProblem(404, 'collection_not_found');
        $this->request('POST', $this->admin('/collections/0199a000-0000-7000-8000-000000000000/publish'));
        $this->assertProblem(404, 'collection_not_found');
    }

    public function test_only_owners_and_event_managers_manage_content(): void
    {
        $this->addMember('pokladna@example.test', Role::Finance);
        $this->addMember('brana@example.test', Role::CheckIn);
        $this->addMember('manazer@example.test', Role::EventManager);

        $this->anonymous();
        $this->request('POST', $this->admin('/collections'), ['title' => 'Galerie']);
        $this->assertProblem(401, 'unauthorized');

        foreach (['pokladna@example.test', 'brana@example.test'] as $email) {
            $this->anonymous();
            $this->login($email);
            $this->request('POST', $this->admin('/collections'), ['title' => 'Galerie']);
            $this->assertProblem(403, 'forbidden');
            $this->request('GET', $this->admin('/collections'));
            $this->assertProblem(403, 'forbidden');
        }

        $this->anonymous();
        $this->login('manazer@example.test');
        $this->createCollection();
    }
}
