<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Catalog;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\CatalogHttpTestCase;

/**
 * FRM-01..09 over HTTP: the draft, the immutable versions, the 422 of an invalid schema, S17 (a required field added
 * after publication) and the permissions of the editor.
 */
final class FormEndpointsTest extends CatalogHttpTestCase
{
    public function test_a_new_event_starts_with_the_default_draft_and_no_published_version(): void
    {
        $this->login(self::OWNER);
        $created = $this->createEvent();

        $overview = $this->request('GET', self::BASE.'/events/'.$created['eventId'].'/form');

        self::assertResponseIsSuccessful();
        self::assertIsArray($overview);
        self::assertNull($overview['published']);
        self::assertSame([], $overview['versions']);
        self::assertSame(1, $overview['draft']['number']);
        self::assertSame(['first_name', 'last_name', 'birth_date', 'orderer_first_name', 'orderer_last_name', 'orderer_email', 'orderer_phone'], array_column($overview['draft']['fields'], 'key'));
        $birth = $overview['draft']['fields'][2];
        self::assertSame('birth_date', $birth['semantic']);
        self::assertSame('date', $birth['jsonSchema']['format']);
    }

    public function test_the_editor_saves_a_draft_with_conditions_and_bindings(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $uri = self::BASE.'/events/'.$event['eventId'].'/form/draft';

        $overview = $this->request('PUT', $uri, ['fields' => [
            ['key' => 'first_name', 'type' => 'short_text', 'label' => 'Jméno', 'scope' => 'person', 'required' => true, 'semantic' => 'first_name'],
            ['key' => 'last_name', 'type' => 'short_text', 'label' => 'Příjmení', 'scope' => 'person', 'required' => true, 'semantic' => 'last_name'],
            ['key' => 'tshirt', 'type' => 'single_choice', 'label' => 'Tričko', 'help' => 'Velikost', 'scope' => 'person', 'constraints' => ['choices' => [['value' => 'S', 'label' => 'S'], ['value' => 'M', 'label' => 'M']]]],
            ['key' => 'team_name', 'type' => 'short_text', 'label' => 'Název týmu', 'scope' => 'group', 'required' => true, 'variantIds' => [$event['relayVariant']]],
            ['key' => 'vegetarian', 'type' => 'yes_no', 'label' => 'Vegetarián', 'scope' => 'person'],
            ['key' => 'allergies', 'type' => 'long_text', 'label' => 'Alergie', 'scope' => 'person', 'condition' => ['type' => 'answer', 'field' => 'vegetarian', 'operator' => 'equals', 'value' => true]],
            ['key' => 'kids_info', 'type' => 'info_text', 'label' => 'Děti', 'help' => 'Děti startují s doprovodem.', 'scope' => 'order', 'condition' => ['type' => 'activity', 'ids' => [$event['trail']]]],
        ]]);

        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertIsArray($overview);
        $fields = array_column($overview['draft']['fields'], null, 'key');
        self::assertSame([$event['relayVariant']], $fields['team_name']['variantIds']);
        self::assertSame('group', $fields['team_name']['scope']);
        self::assertSame(['type' => 'answer', 'field' => 'vegetarian', 'operator' => 'equals', 'value' => true], $fields['allergies']['condition']);
        self::assertSame([['const' => 'S', 'title' => 'S'], ['const' => 'M', 'title' => 'M']], $fields['tshirt']['jsonSchema']['oneOf']);
        self::assertSame('Velikost', $fields['tshirt']['jsonSchema']['description']);
        self::assertSame(1, $overview['draft']['number'], 'the same draft is edited');
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM catalog.form_version WHERE event_id = ?', [$event['eventId']]));
        self::assertSame(2, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'form.draft_changed' AND entity_id = ?", [$overview['draft']['id']]));
    }

    public function test_an_invalid_schema_is_a_422_that_lists_every_problem(): void
    {
        $this->login(self::OWNER);
        $created = $this->createEvent();
        $uri = self::BASE.'/events/'.$created['eventId'].'/form/draft';

        $this->request('PUT', $uri, ['fields' => [
            ['key' => 'Jmeno Prijmeni', 'type' => 'short_text', 'label' => 'Jméno', 'scope' => 'person'],
            ['key' => 'tshirt', 'type' => 'single_choice', 'label' => 'Tričko', 'scope' => 'person'],
            ['key' => 'age', 'type' => 'short_text', 'label' => 'Věk', 'scope' => 'person', 'semantic' => 'birth_date'],
            ['key' => 'dog', 'type' => 'hologram', 'label' => 'Pes', 'scope' => 'person'],
            ['key' => 'when', 'type' => 'date', 'label' => 'Kdy', 'scope' => 'person', 'condition' => ['type' => 'answer', 'field' => 'later', 'operator' => 'filled']],
            ['key' => 'later', 'type' => 'yes_no', 'label' => 'Později', 'scope' => 'person'],
        ]]);

        $problem = $this->assertProblem(422, 'form_schema_invalid');
        self::assertSame(['violationCount' => 5], $problem['context']);
        $byPath = [];
        foreach ($problem['violations'] as $violation) {
            $byPath[$violation['path']] = $violation['code'];
        }
        self::assertSame('invalid_value', $byPath['fields[0].key']);
        self::assertSame('invalid_value', $byPath['fields[1].constraints.choices']);
        self::assertSame('incompatible_semantic', $byPath['fields[2].semantic']);
        self::assertSame('unknown_type', $byPath['fields[3].type']);
        self::assertSame('invalid_reference', $byPath['fields[4].condition.field']);

        // Nothing of it was saved.
        $overview = $this->request('GET', self::BASE.'/events/'.$created['eventId'].'/form');
        self::assertIsArray($overview);
        self::assertCount(7, $overview['draft']['fields']);
    }

    public function test_the_schema_must_have_a_list_of_fields(): void
    {
        $this->login(self::OWNER);
        $created = $this->createEvent();

        $this->request('PUT', self::BASE.'/events/'.$created['eventId'].'/form/draft', ['fields' => 'nope']);

        $problem = $this->assertProblem(422, 'form_schema_invalid');
        self::assertSame('fields', $problem['violations'][0]['path']);
    }

    public function test_fields_may_only_point_to_activities_and_variants_of_this_event(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();

        $this->request('PUT', self::BASE.'/events/'.$event['eventId'].'/form/draft', ['fields' => [
            ['key' => 'dog', 'type' => 'short_text', 'label' => 'Pes', 'scope' => 'person', 'activityIds' => ['0197b4a0-0000-7000-8000-000000000000'], 'variantIds' => [$event['trailVariant']]],
            ['key' => 'vet', 'type' => 'yes_no', 'label' => 'Očkování', 'scope' => 'person', 'condition' => ['type' => 'variant', 'ids' => ['0197b4a0-0000-7000-8000-000000000001']]],
        ]]);

        $problem = $this->assertProblem(422, 'form_schema_invalid');
        self::assertEqualsCanonicalizing(['fields[0].activityIds', 'fields[1].condition.ids'], array_column($problem['violations'], 'path'));
    }

    public function test_s17_a_new_version_leaves_the_old_one_untouched_and_names_the_added_required_field(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $base = self::BASE.'/events/'.$event['eventId'].'/form';
        $this->publish($event['eventId']);
        $v1 = $this->request('GET', $base);
        self::assertIsArray($v1);
        self::assertSame(1, $v1['published']['number']);
        self::assertNull($v1['draft']);
        $v1Hash = $v1['published']['schemaSha256'];

        // Editing a published form starts a draft of version 2 (copy-free: the editor sends the whole schema).
        $fields = array_map(static fn(array $field): array => array_intersect_key($field, array_flip(['key', 'type', 'label', 'help', 'required', 'scope', 'constraints', 'semantic', 'condition', 'activityIds', 'variantIds'])), $v1['published']['fields']);
        $fields = array_map(static function (array $field): array {
            $field['constraints'] = (array) $field['constraints'];

            return $field;
        }, $fields);
        $fields[] = ['key' => 'dog_name', 'type' => 'short_text', 'label' => 'Jméno psa', 'scope' => 'person', 'required' => true];
        $draft = $this->request('PUT', $base.'/draft', ['fields' => $fields]);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertIsArray($draft);
        self::assertSame(2, $draft['draft']['number']);
        self::assertSame($v1Hash, $draft['published']['schemaSha256'], 'version 1 is unchanged by the edit');

        $published = $this->request('POST', $base.'/publish');
        self::assertResponseIsSuccessful();
        self::assertIsArray($published);
        self::assertSame(2, $published['number']);

        $overview = $this->request('GET', $base);
        self::assertIsArray($overview);
        self::assertNull($overview['draft']);
        self::assertSame([2, 1], array_column($overview['versions'], 'number'));
        self::assertNotSame($v1Hash, $overview['published']['schemaSha256']);

        $old = $this->request('GET', $base.'/versions/1');
        self::assertIsArray($old);
        self::assertSame($v1Hash, $old['schemaSha256']);
        self::assertCount(7, $old['fields']);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'form.version_published' AND entity_id = ?", [$published['formVersionId']]));
        self::assertSame(['dog_name'], json_decode($this->dbString("SELECT after->>'addedRequiredFields' FROM audit.audit_entry WHERE action = 'form.version_published' AND entity_id = ?", [$published['formVersionId']]), true));
    }

    public function test_publishing_needs_a_draft_and_a_published_event(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $base = self::BASE.'/events/'.$event['eventId'].'/form';

        $this->request('POST', $base.'/publish');
        $problem = $this->assertProblem(422, 'invalid_catalog_data');
        self::assertStringContainsString('together with the event', (string) $problem['detail']);

        $this->publish($event['eventId']);
        $this->request('POST', $base.'/publish');
        $problem = $this->assertProblem(422, 'invalid_catalog_data');
        self::assertStringContainsString('no draft', (string) $problem['detail']);
    }

    public function test_the_new_version_is_checked_against_the_publication_rules(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $this->publish($event['eventId']);
        $base = self::BASE.'/events/'.$event['eventId'].'/form';
        $this->request('PUT', $base.'/draft', ['fields' => [['key' => 'email', 'type' => 'email', 'label' => 'E-mail', 'scope' => 'order', 'semantic' => 'email', 'required' => true]]]);
        self::assertResponseIsSuccessful();

        $this->request('POST', $base.'/publish');

        $problem = $this->assertProblem(422, 'event_not_publishable');
        self::assertSame(['identity_field_missing', 'identity_field_missing'], array_column($problem['violations'], 'code'));
    }

    public function test_unknown_versions_and_events_are_404(): void
    {
        $this->login(self::OWNER);
        $created = $this->createEvent();

        $this->request('GET', self::BASE.'/events/'.$created['eventId'].'/form/versions/9');
        $this->assertProblem(404, 'form_version_not_found');
        $this->request('GET', self::BASE.'/events/0197b4a0-0000-7000-8000-000000000000/form');
        $this->assertProblem(404, 'event_not_found');
        $this->request('PUT', self::BASE.'/events/0197b4a0-0000-7000-8000-000000000000/form/draft', ['fields' => []]);
        $this->assertProblem(404, 'event_not_found');
    }

    public function test_the_form_of_another_organizer_is_404_and_a_check_in_operator_cannot_edit(): void
    {
        $this->addMember('obsluha@example.test', Role::CheckIn);
        $this->createOwner('petr@example.test', 'cizi-klub');
        $this->login(self::OWNER);
        $created = $this->createEvent();
        $uri = self::BASE.'/events/'.$created['eventId'].'/form';

        $this->login('obsluha@example.test');
        $this->request('GET', $uri);
        self::assertResponseIsSuccessful();
        $this->request('PUT', $uri.'/draft', ['fields' => []]);
        $this->assertProblem(403, 'forbidden');
        $this->request('POST', $uri.'/publish');
        $this->assertProblem(403, 'forbidden');

        $this->login('petr@example.test');
        $this->request('GET', '/api/admin/org/cizi-klub/events/'.$created['eventId'].'/form');
        $this->assertProblem(404, 'event_not_found');
        $this->request('GET', $uri);
        $this->assertProblem(404, 'not_found');
    }

    public function test_anonymous_access_is_401(): void
    {
        $this->request('GET', self::BASE.'/events/0197b4a0-0000-7000-8000-000000000000/form');
        $this->assertProblem(401, 'unauthorized');
        $this->request('PUT', self::BASE.'/events/0197b4a0-0000-7000-8000-000000000000/form/draft', ['fields' => []]);
        $this->assertProblem(401, 'unauthorized');
    }

    public function test_the_editor_can_list_the_field_types(): void
    {
        $this->login(self::OWNER);

        $catalog = $this->request('GET', self::BASE.'/form-field-types');

        self::assertResponseIsSuccessful();
        self::assertIsArray($catalog);
        self::assertCount(11, $catalog['types']);
        self::assertContains('person', $catalog['scopes']);
        $semantic = array_column($catalog['semanticFields'], null, 'name');
        self::assertSame(['date'], $semantic['birth_date']['types']);
        self::assertSame(['phone'], $semantic['guardian_phone']['types']);
    }
}
