<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Content;

use Eventor\Tests\Api\Catalog\CatalogApiTestCase;
use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Support\Catalog\Json;

/**
 * Black-box: post-event content over real HTTP (POST-01..05, POST-07, PUB-06), thumbnails by the running worker.
 */
final class ContentApiTest extends CatalogApiTestCase
{
    private string $eventId;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        $created = $this->createEvent(self::uniqueSlug('obsah'));
        $this->eventId = Json::str($created, 'eventId');
        $this->slug = Json::str($created, 'slug');
        $activity = $this->addActivity($this->eventId, 'Trail 5 km', ['startsAt' => '2026-11-21T10:00:00+01:00']);
        $this->addVariant($this->eventId, $activity, 'Jednotlivec', 30000, 100);
        self::assertSame(200, $this->owner->request('PUT', self::ORG.'/events/'.$this->eventId.'/payment-rules', ['methods' => ['cash'], 'dueDays' => 7, 'holdTtlHours' => 48])->status);
        $published = $this->owner->post(self::ORG.'/events/'.$this->eventId.'/publish');
        self::assertSame(200, $published->status, $published->body);
    }

    public function test_a_gallery_is_uploaded_published_and_shown_on_the_public_page(): void
    {
        $collection = $this->ok($this->owner->post($this->admin('/collections'), ['title' => 'Fotky']), 201);
        $collectionId = Json::str($collection, 'collectionId');
        $photo = $this->owner->upload($this->admin('/collections/'.$collectionId.'/photos'), 'photo', 'start.jpg', self::jpeg(), 'image/jpeg', ['description' => 'Start']);
        self::assertSame(201, $photo->status, $photo->body);
        $photoId = Json::str($photo->json(), 'photoId');

        self::assertSame([], Json::arr($this->ok($this->anonymous()->get($this->public('/content'))), 'collections'), 'a draft is not public');
        $this->ok($this->owner->post($this->admin('/collections/'.$collectionId.'/publish')));

        $page = $this->ok($this->anonymous()->get($this->public('/content')));
        self::assertSame('Fotky', Json::str($page, 'collections.0.title'));
        self::assertSame(1, Json::int($page, 'collections.0.photoCount'));

        $original = $this->anonymous()->get('/api/public/content/photos/'.$photoId);
        self::assertSame(200, $original->status);
        self::assertSame('image/jpeg', $original->header('Content-Type'));
        $thumbnail = $this->waitForThumbnail($photoId);
        self::assertSame(200, $thumbnail->status, 'the worker makes the thumbnail');
    }

    public function test_link_only_external_and_errors(): void
    {
        $link = $this->ok($this->owner->post($this->admin('/collections'), ['title' => 'Rodiče', 'visibility' => 'link_only']), 201);
        $this->ok($this->owner->post($this->admin('/collections/'.Json::str($link, 'collectionId').'/publish')));
        $opened = $this->anonymous()->get($this->public('/collection-links/'.Json::str($link, 'linkToken')));
        self::assertSame(200, $opened->status, $opened->body);
        $this->assertProblem($this->anonymous()->get($this->public('/collection-links/'.str_repeat('a', 48))), 404, 'collection_not_found');

        $external = $this->ok($this->owner->post($this->admin('/collections'), ['title' => 'Fotograf', 'externalUrl' => 'https://galerie.example.test/x']), 201);
        $this->assertProblem($this->owner->upload($this->admin('/collections/'.Json::str($external, 'collectionId').'/photos'), 'photo', 'a.jpg', self::jpeg(), 'image/jpeg'), 409, 'collection_is_external');

        $this->assertProblem($this->owner->post($this->admin('/collections'), ['title' => '']), 422, 'validation_failed');
        $this->assertProblem($this->owner->upload($this->admin('/collections/'.Json::str($link, 'collectionId').'/photos'), 'photo', 'a.txt', 'plain text', 'text/plain'), 422, 'invalid_photo');
        $this->assertProblem($this->anonymous()->post($this->admin('/collections'), ['title' => 'X']), 401, 'unauthorized');
        $this->assertProblem($this->loginAs(ApiFixtures::BETA)->post($this->admin('/collections'), ['title' => 'X']), 404, 'not_found');
        $this->assertProblem($this->owner->post($this->admin('/collections/0199a000-0000-7000-8000-000000000000/publish')), 404, 'collection_not_found');
    }

    public function test_materials_archive_and_closing_message(): void
    {
        $material = $this->ok($this->owner->post($this->admin('/materials'), ['title' => 'Výsledky', 'url' => 'https://vysledky.example.test', 'type' => 'results']), 201);
        $materialId = Json::str($material, 'materialId');
        self::assertSame([], Json::arr($this->ok($this->anonymous()->get($this->public('/content'))), 'materials'));
        $this->ok($this->owner->post($this->admin('/materials/'.$materialId.'/publish')));
        self::assertSame(['Výsledky'], Json::pluck($this->ok($this->anonymous()->get($this->public('/content'))), 'materials', 'title'));
        $this->ok($this->owner->request('PUT', $this->admin('/materials/'.$materialId.'/archive'), ['inArchive' => true]));
        self::assertTrue(Json::at($this->ok($this->owner->get($this->admin('/materials'))), 'items.0.inArchive'));

        $this->assertProblem($this->owner->post($this->admin('/materials'), ['title' => 'X', 'url' => 'ftp://x']), 422, 'validation_failed');
        $this->assertProblem($this->anonymous()->get($this->public('/archive')), 404, 'event_not_found');

        $campaign = $this->ok($this->owner->post($this->admin('/closing-message')), 201);
        self::assertNotSame('', Json::str($campaign, 'campaignId'));
    }

    private function admin(string $suffix): string
    {
        return self::ORG.'/events/'.$this->eventId.$suffix;
    }

    private function public(string $suffix): string
    {
        return '/api/public/orgs/'.ApiFixtures::ALPHA['slug'].'/events/'.$this->slug.$suffix;
    }

    private function waitForThumbnail(string $photoId): \Eventor\Tests\Api\Support\ApiResponse
    {
        for ($attempt = 0; $attempt < 30; ++$attempt) {
            $photos = $this->owner->get('/api/public/content/photos/'.$photoId.'/thumbnail');
            if ('image/jpeg' === $photos->header('Content-Type') && 200 === $photos->status && $this->thumbnailReady($photoId)) {
                return $photos;
            }
            usleep(200_000);
        }

        return $this->owner->get('/api/public/content/photos/'.$photoId.'/thumbnail');
    }

    private function thumbnailReady(string $photoId): bool
    {
        foreach (Json::arr($this->ok($this->owner->get($this->admin('/collections'))), 'items') as $collection) {
            $photos = $this->ok($this->owner->get($this->admin('/collections/'.Json::str($collection, 'id').'/photos')));
            foreach (Json::arr($photos, 'items') as $photo) {
                if ($photoId === Json::str($photo, 'id')) {
                    return true === Json::at($photo, 'hasThumbnail');
                }
            }
        }

        return false;
    }

    private static function jpeg(): string
    {
        $image = imagecreatetruecolor(900, 600);
        \assert(false !== $image);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }
}
