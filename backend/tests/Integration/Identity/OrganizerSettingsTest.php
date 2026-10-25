<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity;

use Eventor\Identity\Contract\Event\PairrotConnected;
use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Identity\Contract\OverpaymentHandling;
use Eventor\Payments\Contract\Exception\PairrotUnavailable;
use Eventor\Tests\Integration\Identity\Support\FakePairrotOnboarding;
use Eventor\Tests\Integration\Identity\Support\IdentityHttpTestCase;
use Eventor\Tests\Integration\Identity\Support\Json;

/**
 * ORG-01 profile, ORG-02 payment settings and the Pairrot wizard, ORG-05 branding fields.
 */
final class OrganizerSettingsTest extends IdentityHttpTestCase
{
    public function test_profile_is_readable_and_editable_by_the_owner(): void
    {
        $this->createOwner('jana@example.test', 'klub', 'Klub Plzeň');
        $this->login('jana@example.test');

        $this->request('PUT', '/api/admin/org/klub/profile', [
            'name' => 'Klub Plzeň z.s.',
            'contactEmail' => 'info@klub.example',
            'contactPhone' => '+420 777 000 111',
            'legalName' => 'Klub Plzeň, z.s.',
            'companyId' => '12345678',
            'vatId' => 'CZ12345678',
            'billingStreet' => 'Hlavní 1',
            'billingCity' => 'Plzeň',
            'billingPostalCode' => '30100',
            'billingCountry' => 'cz',
            'logoUrl' => 'https://klub.example/logo.png',
            'brandColor' => '#1a73e8',
        ]);
        self::assertResponseIsSuccessful();

        $profile = $this->request('GET', '/api/admin/org/klub/profile');
        self::assertIsArray($profile);
        self::assertSame('Klub Plzeň z.s.', $profile['name']);
        self::assertSame('klub', $profile['slug']);
        self::assertSame('info@klub.example', $profile['contactEmail']);
        self::assertSame('CZ', $profile['billingCountry']);
        self::assertSame('12345678', $profile['companyId']);
        self::assertSame('#1a73e8', $profile['brandColor']);
        self::assertNull($profile['coverImageUrl']);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'organizer.profile_updated'"));
    }

    public function test_profile_validation_is_a_422(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $this->request('PUT', '/api/admin/org/klub/profile', ['name' => '', 'contactEmail' => 'nope', 'brandColor' => 'red', 'logoUrl' => 'not a url', 'billingCountry' => 'XX1']);

        $problem = $this->assertProblem(422, 'validation_failed');
        $paths = array_column(Json::rows($problem, 'violations'), 'path');
        foreach (['name', 'contactEmail', 'brandColor', 'logoUrl', 'billingCountry'] as $path) {
            self::assertContains($path, $paths);
        }
    }

    public function test_only_the_owner_edits_the_profile_but_every_member_reads_it(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->joinAs('jana@example.test', 'klub', 'fin@example.test', 'FINANCE');

        $this->request('GET', '/api/admin/org/klub/profile');
        self::assertResponseIsSuccessful();

        $this->request('PUT', '/api/admin/org/klub/profile', ['name' => 'Hacked']);
        $this->assertProblem(403, 'forbidden');
        self::assertSame('Klub', $this->dbString("SELECT name FROM identity.organizer WHERE slug = 'klub'"));
    }

    public function test_payment_settings_have_defaults_and_can_be_changed(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $defaults = $this->request('GET', '/api/admin/org/klub/payment-settings');
        self::assertIsArray($defaults);
        self::assertSame(14, $defaults['defaultDueDays']);
        self::assertSame(48, $defaults['defaultHoldTtlHours']);
        self::assertFalse($defaults['autoRestoreLatePayment']);
        self::assertSame('ask', $defaults['overpaymentHandling']);
        self::assertFalse($defaults['pairrotConnected']);

        $saved = $this->request('PUT', '/api/admin/org/klub/payment-settings', [
            'defaultDueDays' => 7,
            'defaultHoldTtlHours' => 24,
            'autoRestoreLatePayment' => true,
            'overpaymentHandling' => 'refund',
            'checkInMayRecordCash' => true,
            'eventManagerMayResolvePayments' => true,
        ]);
        self::assertResponseIsSuccessful();
        self::assertIsArray($saved);
        self::assertSame(7, $saved['defaultDueDays']);
        self::assertSame('refund', $saved['overpaymentHandling']);

        // ... and the Contract facade other modules read agrees.
        $slugId = $this->dbString("SELECT id FROM identity.organizer WHERE slug = 'klub'");
        $policy = $this->service(OrganizerSettings::class)->policy(\Eventor\Shared\Domain\Id\OrganizerId::fromString($slugId));
        self::assertSame(7, $policy->defaultDueDays);
        self::assertSame(24, $policy->defaultHoldTtlHours);
        self::assertTrue($policy->autoRestoreLatePayment);
        self::assertSame(OverpaymentHandling::Refund, $policy->overpaymentHandling);
        self::assertTrue($policy->checkInMayRecordCash);
        self::assertTrue($policy->eventManagerMayResolvePayments);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'organizer.policy_changed'"));
    }

    public function test_payment_settings_validation_and_permission(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $this->request('PUT', '/api/admin/org/klub/payment-settings', ['defaultDueDays' => 0, 'defaultHoldTtlHours' => 9999, 'overpaymentHandling' => 'keep']);
        $this->assertProblem(422, 'validation_failed');

        $this->joinAs('jana@example.test', 'klub', 'fin@example.test', 'FINANCE');
        $this->request('GET', '/api/admin/org/klub/payment-settings');
        $this->assertProblem(403, 'forbidden');
        $this->request('PUT', '/api/admin/org/klub/payment-settings', ['defaultDueDays' => 3]);
        $this->assertProblem(403, 'forbidden');
    }

    public function test_the_policy_exceptions_switch_the_conditional_matrix_cells(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $this->request('PUT', '/api/admin/org/klub/payment-settings', ['checkInMayRecordCash' => true]);

        $organizer = \Eventor\Shared\Domain\Id\OrganizerId::fromString($this->dbString("SELECT id FROM identity.organizer WHERE slug = 'klub'"));
        $this->joinAs('jana@example.test', 'klub', 'door@example.test', 'CHECK_IN');
        $policy = $this->service(\Eventor\Identity\Contract\AccessPolicy::class);

        self::assertTrue($policy->isAllowed(\Eventor\Identity\Contract\Permission::CashRecord, $organizer, 'door@example.test'));
        self::assertFalse($policy->isAllowed(\Eventor\Identity\Contract\Permission::CashCorrect, $organizer, 'door@example.test'));
        self::assertFalse($policy->isAllowed(\Eventor\Identity\Contract\Permission::PaymentExceptionResolve, $organizer, 'door@example.test'));
    }

    public function test_the_pairrot_wizard_stores_the_connection_and_publishes_pairrot_connected(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $this->client->disableReboot();

        $body = $this->request('POST', '/api/admin/org/klub/payment-settings/pairrot', ['accountNumberOrIban' => 'CZ6508000000192000145399', 'label' => 'Hlavní']);

        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertTrue(Json::at($body, 'settings', 'pairrotConnected'));
        self::assertTrue(Json::at($body, 'settings', 'transferEnabled'));
        self::assertSame('CZ65****5399', Json::at($body, 'settings', 'maskedIban'));
        self::assertStringEndsWith('@pairrot.test', Json::str($body, 'inboundAddress'));

        $row = $this->dbRow("SELECT pairrot_subtenant_id, pairrot_bank_connection_id FROM identity.organizer WHERE slug = 'klub'");
        self::assertIsString($row['pairrot_subtenant_id']);
        self::assertStringStartsWith('eventor_org_', $row['pairrot_subtenant_id']);
        $fake = static::getContainer()->get(FakePairrotOnboarding::class);
        \assert($fake instanceof FakePairrotOnboarding);
        self::assertSame(['activateSubtenant:jana@example.test', 'connectBankAccount:'.$row['pairrot_subtenant_id']], $fake->calls);

        $events = $this->published(PairrotConnected::class);
        self::assertCount(1, $events);
        self::assertSame($row['pairrot_subtenant_id'], $events[0]->subtenantId);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'organizer.pairrot_connected'"));

        $settings = $this->request('GET', '/api/admin/org/klub/payment-settings');
        self::assertTrue(Json::at($settings, 'pairrotConnected'));
    }

    public function test_pairrot_failures_surface_as_problems_and_store_nothing(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $this->client->disableReboot();

        $this->request('POST', '/api/admin/org/klub/payment-settings/pairrot', ['accountNumberOrIban' => 'CZ0000000000000000000000']);
        $this->assertProblem(409, 'bank_account_unavailable');

        $fake = static::getContainer()->get(FakePairrotOnboarding::class);
        \assert($fake instanceof FakePairrotOnboarding);
        $fake->failWith(PairrotUnavailable::because('Pairrot is down'));
        $this->request('POST', '/api/admin/org/klub/payment-settings/pairrot', ['accountNumberOrIban' => 'CZ6508000000192000145399']);
        $this->assertProblem(503, 'pairrot_unavailable');

        self::assertNull($this->connection()->fetchOne("SELECT pairrot_subtenant_id FROM identity.organizer WHERE slug = 'klub'"));
    }

    public function test_the_wizard_needs_a_contact_email_validation_and_the_owner(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $this->request('POST', '/api/admin/org/klub/payment-settings/pairrot', ['accountNumberOrIban' => '']);
        $this->assertProblem(422, 'validation_failed');

        $this->request('PUT', '/api/admin/org/klub/profile', ['name' => 'Klub']); // clears the contact e-mail
        $this->request('POST', '/api/admin/org/klub/payment-settings/pairrot', ['accountNumberOrIban' => 'CZ6508000000192000145399']);
        $this->assertProblem(422, 'contact_email_required');

        $this->joinAs('jana@example.test', 'klub', 'em@example.test', 'EVENT_MANAGER');
        $this->request('POST', '/api/admin/org/klub/payment-settings/pairrot', ['accountNumberOrIban' => 'CZ6508000000192000145399']);
        $this->assertProblem(403, 'forbidden');
    }

    public function test_settings_of_a_foreign_organizer_are_404(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->createOwner('petr@example.test', 'cizi');
        $this->login('jana@example.test');

        foreach (['/profile', '/payment-settings', '/members', '/device-tokens'] as $path) {
            $this->request('GET', '/api/admin/org/cizi'.$path);
            $this->assertProblem(404, 'not_found');
        }
    }

    public function test_unauthenticated_access_is_401(): void
    {
        $this->createOwner('jana@example.test', 'klub');

        $this->request('GET', '/api/admin/org/klub/profile');

        $this->assertProblem(401, 'unauthorized');
    }
}
