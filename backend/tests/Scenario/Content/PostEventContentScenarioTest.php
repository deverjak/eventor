<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario\Content;

use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\Content\ContentHttpTestCase;
use Symfony\Component\Clock\Clock;

/**
 * After "Borský podzimní víkend 2026" (POST-01..05, POST-07, PUB-06): the organizer uploads the photos, links the
 * photographer's gallery and the results, publishes, thanks the checked-in participants and, once the event is over,
 * keeps the best of it in the public archive. Visitors see only what was published, and never personal data.
 */
final class PostEventContentScenarioTest extends ContentHttpTestCase
{
    public function test_the_content_of_a_finished_event(): void
    {
        // The organizer prepares the gallery during the weekend.
        $gallery = $this->createCollection('Sobota v Borském parku');
        $photos = [];
        foreach (['Start', 'Cíl', 'Vyhlášení'] as $description) {
            $photos[] = Json::str($this->upload($gallery['collectionId'], self::jpeg(1600, 1000), ['description' => $description, 'author' => 'Foto Plzeň']), 'photoId');
        }
        self::assertSame(3, $this->dbInt('SELECT count(*) FROM content.photo WHERE collection_id = ? AND thumbnail_key IS NOT NULL', [$gallery['collectionId']]), 'every photo got its thumbnail in the worker');
        $photographer = $this->createCollection('Galerie fotografa', 'public', 'https://galerie.example.test/borsky-2026');
        $family = $this->createCollection('Pro rodiče dětí', 'link_only');
        $this->upload($family['collectionId'], self::jpeg(800, 1200), ['description' => 'Dětský závod']);
        $results = Json::str($this->request('POST', $this->admin('/materials'), ['title' => 'Výsledky', 'url' => 'https://vysledky.example.test/borsky', 'type' => 'results']), 'materialId');

        // Nothing is public before it is published.
        $this->anonymous();
        self::assertSame(['collections' => [], 'materials' => []], $this->request('GET', $this->public('/content')));

        // Monday: publish and propose the thank-you to the checked-in participants.
        $this->login(self::OWNER);
        foreach ([$gallery['collectionId'], $photographer['collectionId'], $family['collectionId']] as $collection) {
            $this->request('POST', $this->admin('/collections/'.$collection.'/publish'));
            $this->drain();
        }
        $this->request('POST', $this->admin('/materials/'.$results.'/publish'));
        $campaignId = Json::str($this->request('POST', $this->admin('/closing-message')), 'campaignId');
        self::assertSame('draft', $this->dbString('SELECT state FROM communication.campaign WHERE id = ?', [$campaignId]));

        $this->anonymous();
        $page = $this->request('GET', $this->public('/content'));
        self::assertSame(['Sobota v Borském parku', 'Galerie fotografa'], Json::pluck($page, 'collections', 'title'), 'the link-only collection is not listed');
        self::assertSame(['Výsledky'], Json::pluck($page, 'materials', 'title'));
        self::assertSame(['Start', 'Cíl', 'Vyhlášení'], array_map(
            fn(mixed $id): string => $this->dbString('SELECT description FROM content.photo WHERE id = ?', [$id]),
            Json::pluck($this->request('GET', $this->public('/collections/'.$gallery['collectionId'].'/photos')), 'items', 'id'),
        ), 'in upload order');
        self::assertCount(1, Json::arr($this->request('GET', $this->public('/collection-links/'.(string) $family['linkToken'])), 'items'));

        // After the event: the organizer keeps the gallery and the results in the public archive.
        $this->login(self::OWNER);
        $this->request('PUT', $this->admin('/collections/'.$gallery['collectionId'].'/archive'), ['inArchive' => true]);
        $this->request('PUT', $this->admin('/materials/'.$results.'/archive'), ['inArchive' => true]);
        Clock::get()->sleep(23 * 86400);
        $this->anonymous();
        $this->login(self::OWNER);
        $this->request('POST', '/api/admin/org/klub/events/'.$this->eventId.'/complete');
        self::assertResponseIsSuccessful();

        $this->anonymous();
        $archive = $this->request('GET', $this->public('/archive'));
        self::assertSame(['Sobota v Borském parku'], Json::pluck($archive, 'collections', 'title'));
        self::assertSame(['Výsledky'], Json::pluck($archive, 'materials', 'title'));
        self::assertStringNotContainsString('@', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', '/api/public/content/photos/'.$photos[0].'/thumbnail');
        self::assertResponseIsSuccessful();
    }
}
