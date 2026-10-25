<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Catalog;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\CatalogHttpTestCase;
use Eventor\Tests\Support\Catalog\Json;

/**
 * PRG-01..04: the program (optionally linked to activities), the importance of a change, and news posts with their
 * draft / published life.
 */
final class ProgramAndNewsTest extends CatalogHttpTestCase
{
    public function test_program_items_are_added_changed_listed_and_removed(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $base = self::BASE.'/events/'.$event['eventId'].'/program';

        $added = $this->request('POST', $base, ['title' => 'Rozcvička', 'startsAt' => '2026-11-21T07:30:00+01:00', 'endsAt' => '2026-11-21T08:00:00+01:00', 'location' => 'Louka', 'responsible' => 'Petr', 'visibility' => 'participants']);
        self::assertResponseStatusCodeSame(201);
        self::assertIsArray($added);
        $this->request('POST', $base, ['title' => 'Start Trailu 5 km', 'startsAt' => '2026-11-21T10:00:00+01:00', 'activityId' => $event['trail']]);
        self::assertResponseStatusCodeSame(201);

        $list = $this->request('GET', $base);
        self::assertIsArray($list);
        self::assertSame(['Rozcvička', 'Start Trailu 5 km'], Json::column(Json::at($list, 'items'), 'title'));
        self::assertSame(Json::at($event, 'trail'), Json::at($list, 'items.1.activityId'));
        self::assertSame('participants', Json::at($list, 'items.0.visibility'));
        self::assertSame('Petr', Json::at($list, 'items.0.responsible'));

        $updated = $this->request('PUT', $base.'/'.Json::str($added, 'programItemId'), ['title' => 'Rozcvička (nová)', 'startsAt' => '2026-11-21T07:45:00+01:00', 'important' => true]);
        self::assertResponseIsSuccessful();
        self::assertIsArray($updated);
        self::assertSame('Rozcvička (nová)', Json::at($updated, 'items.0.title'));
        self::assertSame('public', Json::at($updated, 'items.0.visibility'));

        $this->request('DELETE', $base.'/'.Json::str($added, 'programItemId'), null);
        self::assertResponseIsSuccessful();
        $after = $this->request('GET', $base);
        self::assertIsArray($after);
        self::assertCount(1, Json::arr($after, 'items'));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'program_item.removed' AND entity_id = ?", [$added['programItemId']]));
        self::assertSame(4, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE entity_type = 'program_item' AND (after->>'eventId') = ?", [$event['eventId']]));
    }

    public function test_a_program_item_validates_its_times_and_its_activity(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $base = self::BASE.'/events/'.$event['eventId'].'/program';

        $this->request('POST', $base, ['title' => '', 'startsAt' => '2026-11-21T07:30:00+01:00', 'visibility' => 'secret']);
        $problem = $this->assertProblem(422, 'validation_failed');
        self::assertEqualsCanonicalizing(['title', 'visibility'], Json::column(Json::at($problem, 'violations'), 'path'));

        $this->request('POST', $base, ['title' => 'Pozpátku', 'startsAt' => '2026-11-21T09:00:00+01:00', 'endsAt' => '2026-11-21T08:00:00+01:00']);
        $this->assertProblem(422, 'invalid_catalog_data');

        $this->request('POST', $base, ['title' => 'Cizí aktivita', 'startsAt' => '2026-11-21T09:00:00+01:00', 'activityId' => '0197b4a0-0000-7000-8000-000000000000']);
        $this->assertProblem(404, 'activity_not_found');

        $this->request('PUT', $base.'/0197b4a0-0000-7000-8000-000000000000', ['title' => 'x', 'startsAt' => '2026-11-21T09:00:00+01:00']);
        $this->assertProblem(404, 'program_item_not_found');
    }

    public function test_the_program_needs_program_manage_to_change_but_not_to_read(): void
    {
        $this->addMember('finance@example.test', Role::Finance);
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $base = self::BASE.'/events/'.$event['eventId'].'/program';

        $this->login('finance@example.test');
        $this->request('GET', $base);
        self::assertResponseIsSuccessful();
        $this->request('POST', $base, ['title' => 'Cena', 'startsAt' => '2026-11-21T09:00:00+01:00']);
        $this->assertProblem(403, 'forbidden');
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/news', ['title' => 'Ahoj', 'body' => 'Text']);
        $this->assertProblem(403, 'forbidden');
    }

    public function test_news_is_drafted_edited_and_published_once(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $base = self::BASE.'/events/'.$event['eventId'].'/news';

        $draft = $this->request('POST', $base, ['title' => 'Start posunut', 'body' => 'Start je v 11:00.', 'priority' => 3, 'broadcast' => true]);
        self::assertResponseStatusCodeSame(201);
        self::assertIsArray($draft);
        self::assertFalse($draft['published']);
        self::assertSame($this->owner['userAccountId'], Json::at($draft, 'items.0.authorAccountId'));
        self::assertTrue(Json::at($draft, 'items.0.broadcast'));

        $this->request('PUT', $base.'/'.Json::str($draft, 'newsPostId'), ['title' => 'Start je v 11:15', 'body' => 'Opravujeme.', 'priority' => 5]);
        self::assertResponseIsSuccessful();

        $this->request('POST', $base.'/'.Json::str($draft, 'newsPostId').'/publish');
        self::assertResponseIsSuccessful();
        $list = $this->request('GET', $base);
        self::assertIsArray($list);
        self::assertTrue(Json::at($list, 'items.0.published'));
        self::assertSame('Start je v 11:15', Json::at($list, 'items.0.title'));
        self::assertSame(5, Json::at($list, 'items.0.priority'));

        $this->request('POST', $base.'/'.Json::str($draft, 'newsPostId').'/publish');
        $this->assertProblem(409, 'news_post_already_published');
        $this->request('PUT', $base.'/'.Json::str($draft, 'newsPostId'), ['title' => 'Zpět', 'body' => 'x']);
        $this->assertProblem(409, 'news_post_already_published');
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'news_post.published' AND entity_id = ?", [$draft['newsPostId']]));
    }

    public function test_a_post_can_be_published_at_once_and_is_validated(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $base = self::BASE.'/events/'.$event['eventId'].'/news';

        $published = $this->request('POST', $base, ['title' => 'Vítejte', 'body' => 'Těšíme se.', 'publish' => true]);
        self::assertResponseStatusCodeSame(201);
        self::assertIsArray($published);
        self::assertTrue($published['published']);

        $this->request('POST', $base, ['title' => '', 'body' => '', 'priority' => 99]);
        $problem = $this->assertProblem(422, 'validation_failed');
        self::assertEqualsCanonicalizing(['title', 'body', 'priority'], Json::column(Json::at($problem, 'violations'), 'path'));

        $this->request('POST', $base.'/0197b4a0-0000-7000-8000-000000000000/publish');
        $this->assertProblem(404, 'news_post_not_found');
    }

    public function test_program_and_news_of_another_organizer_are_not_found(): void
    {
        $this->createOwner('petr@example.test', 'cizi-klub');
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();

        $this->login('petr@example.test');
        $this->request('GET', '/api/admin/org/cizi-klub/events/'.$event['eventId'].'/program');
        $this->assertProblem(404, 'event_not_found');
        $this->request('POST', '/api/admin/org/cizi-klub/events/'.$event['eventId'].'/news', ['title' => 'x', 'body' => 'y']);
        $this->assertProblem(404, 'event_not_found');
        $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/program');
        $this->assertProblem(404, 'not_found');
    }

    public function test_anonymous_access_is_401(): void
    {
        $this->request('GET', self::BASE.'/events/0197b4a0-0000-7000-8000-000000000000/program');
        $this->assertProblem(401, 'unauthorized');
        $this->request('POST', self::BASE.'/events/0197b4a0-0000-7000-8000-000000000000/news', ['title' => 'x', 'body' => 'y']);
        $this->assertProblem(401, 'unauthorized');
    }
}
