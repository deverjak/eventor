<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Content;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\Content\ContentHttpTestCase;
use Symfony\Component\Clock\Clock;

/**
 * POST-04 materials, POST-07 the public archive of a finished event, POST-05 the closing message.
 */
final class MaterialEndpointsTest extends ContentHttpTestCase
{
    public function test_a_material_is_a_draft_until_published_and_can_be_taken_back(): void
    {
        $created = $this->request('POST', $this->admin('/materials'), ['title' => 'Výsledky', 'url' => 'https://vysledky.example.test/borsky', 'type' => 'results', 'position' => 1]);
        self::assertResponseStatusCodeSame(201);
        $materialId = Json::str($created, 'materialId');

        self::assertSame([['id' => $materialId, 'title' => 'Výsledky', 'url' => 'https://vysledky.example.test/borsky', 'type' => 'results', 'position' => 1, 'published' => false, 'inArchive' => false]], Json::arr($this->request('GET', $this->admin('/materials')), 'items'));
        self::assertSame([], Json::arr($this->request('GET', $this->public('/content')), 'materials'));

        $this->request('POST', $this->admin('/materials/'.$materialId.'/publish'));
        self::assertResponseIsSuccessful();
        self::assertSame([['title' => 'Výsledky', 'url' => 'https://vysledky.example.test/borsky', 'mediaType' => 'results']], Json::arr($this->request('GET', $this->public('/content')), 'materials'));

        $this->request('POST', $this->admin('/materials/'.$materialId.'/unpublish'));
        self::assertSame([], Json::arr($this->request('GET', $this->public('/content')), 'materials'));
        self::assertSame(3, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE entity_type = 'material' AND entity_id = ?", [$materialId]), 'added, published, unpublished');
    }

    public function test_materials_are_validated_and_scoped_to_their_event(): void
    {
        $this->request('POST', $this->admin('/materials'), ['title' => 'X', 'url' => 'http://insecure.example.test']);
        $this->assertProblem(422, 'validation_failed');
        $this->request('POST', $this->admin('/materials'), ['title' => 'X', 'url' => 'https://ok.example.test', 'type' => 'podcast']);
        $this->assertProblem(422, 'validation_failed');
        $this->request('POST', $this->admin('/materials'), ['title' => '', 'url' => 'https://ok.example.test']);
        $this->assertProblem(422, 'validation_failed');

        $materialId = Json::str($this->request('POST', $this->admin('/materials'), ['title' => 'Video', 'url' => 'https://video.example.test/1', 'type' => 'video']), 'materialId');
        $other = $this->createPublishableEvent('jine');
        $this->publish($other['eventId']);
        $this->drain();
        $this->request('POST', $this->admin('/materials/'.$materialId.'/publish', $other['eventId']));
        $this->assertProblem(404, 'material_not_found');

        $this->addMember('pokladna@example.test', Role::Finance);
        $this->anonymous();
        $this->login('pokladna@example.test');
        $this->request('POST', $this->admin('/materials'), ['title' => 'X', 'url' => 'https://ok.example.test']);
        $this->assertProblem(403, 'forbidden');
        $this->anonymous();
        $this->request('POST', $this->admin('/materials/'.$materialId.'/publish'));
        $this->assertProblem(401, 'unauthorized');
    }

    public function test_the_public_archive_of_a_finished_event_shows_only_designated_content(): void
    {
        $kept = $this->createCollection('Nejlepší fotky');
        $this->upload($kept['collectionId']);
        $notKept = $this->createCollection('Všechny fotky');
        $results = Json::str($this->request('POST', $this->admin('/materials'), ['title' => 'Výsledky', 'url' => 'https://vysledky.example.test', 'type' => 'results']), 'materialId');
        $survey = Json::str($this->request('POST', $this->admin('/materials'), ['title' => 'Anketa', 'url' => 'https://anketa.example.test']), 'materialId');
        foreach ([$kept['collectionId'], $notKept['collectionId']] as $collection) {
            $this->request('POST', $this->admin('/collections/'.$collection.'/publish'));
            $this->drain();
        }
        foreach ([$results, $survey] as $material) {
            $this->request('POST', $this->admin('/materials/'.$material.'/publish'));
        }
        $this->request('PUT', $this->admin('/collections/'.$kept['collectionId'].'/archive'), ['inArchive' => true]);
        self::assertResponseIsSuccessful();
        $this->request('PUT', $this->admin('/materials/'.$results.'/archive'), ['inArchive' => true]);
        self::assertResponseIsSuccessful();

        $this->request('GET', $this->public('/archive'));
        $this->assertProblem(404, 'event_not_found');

        Clock::get()->sleep(23 * 86400); // after the end of the event (22. 11.); the 7-day session is over too
        $this->anonymous();
        $this->login(self::OWNER);
        $this->request('POST', '/api/admin/org/klub/events/'.$this->eventId.'/complete');
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        $this->anonymous();

        $archive = $this->request('GET', $this->public('/archive'));
        self::assertSame(['Nejlepší fotky'], Json::pluck($archive, 'collections', 'title'));
        self::assertSame(['Výsledky'], Json::pluck($archive, 'materials', 'title'));
        $json = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('@', $json, 'no e-mail address or other personal data in the archive');
    }

    public function test_the_closing_message_is_proposed_to_the_checked_in_participants_only(): void
    {
        $created = $this->request('POST', $this->admin('/closing-message'));
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        $campaignId = Json::str($created, 'campaignId');

        $campaign = $this->dbRow('SELECT state, template_key, audience::text AS audience, event_id FROM communication.campaign WHERE id = ?', [$campaignId]);
        self::assertSame('draft', $campaign['state'], 'the organizer reviews and sends it in Communication');
        self::assertSame('post_event', $campaign['template_key']);
        self::assertSame($this->eventId, $campaign['event_id']);
        self::assertTrue(Json::at(json_decode(Json::str($campaign, 'audience'), true, 512, \JSON_THROW_ON_ERROR), 'checkedIn'));

        $this->addMember('brana@example.test', Role::CheckIn);
        $this->anonymous();
        $this->login('brana@example.test');
        $this->request('POST', $this->admin('/closing-message'));
        $this->assertProblem(403, 'forbidden');
    }
}
