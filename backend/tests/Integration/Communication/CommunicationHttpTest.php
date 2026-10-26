<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Communication;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\Registration\RegistrationHttpTestCase;

/**
 * The HTTP surface of Communication through the in-process kernel: status codes and problem details, 401 without a
 * session, 403 for a role without `message.send` (and for the reminder schedule, an owner-only setting), 404 for a
 * foreign organizer, and the targeting against real orders of Registration.
 */
final class CommunicationHttpTest extends RegistrationHttpTestCase
{
    public function test_without_a_session_everything_is_401(): void
    {
        foreach ([['GET', '/message-templates'], ['GET', '/campaigns'], ['GET', '/deliveries'], ['GET', '/suppressions'], ['GET', '/communication/reminder-settings']] as [$method, $path]) {
            $this->request($method, self::BASE.$path);
            $this->assertProblem(401, 'unauthorized');
        }
    }

    public function test_a_role_without_the_permission_gets_403(): void
    {
        $this->addMember('fin@example.test', Role::Finance);
        $this->addMember('door@example.test', Role::CheckIn);
        $this->addMember('manager@example.test', Role::EventManager);

        foreach (['fin@example.test', 'door@example.test'] as $email) {
            $this->login($email);
            foreach ([['GET', '/message-templates'], ['GET', '/campaigns'], ['GET', '/deliveries'], ['GET', '/suppressions']] as [$method, $path]) {
                $this->request($method, self::BASE.$path);
                $this->assertProblem(403, 'forbidden');
            }
        }

        $this->login('manager@example.test');
        $this->request('GET', self::BASE.'/campaigns');
        self::assertResponseStatusCodeSame(200);
        $this->request('GET', self::BASE.'/communication/reminder-settings');
        $this->assertProblem(403, 'forbidden');
    }

    public function test_a_foreign_organizer_is_404(): void
    {
        $this->createOwner('other@example.test', 'other');
        $this->login(self::OWNER);

        foreach (['/message-templates', '/campaigns', '/deliveries', '/suppressions', '/communication/reminder-settings'] as $path) {
            $this->request('GET', '/api/admin/org/other'.$path);
            $this->assertProblem(404, 'not_found');
        }
    }

    public function test_the_campaign_preview_counts_the_deliveries_the_audience_gives(): void
    {
        $event = $this->openEvent(kids: true);
        $this->publishAndOpen($event['eventId']);
        $this->placeOrder(self::orderBody($event, 'Anna', 'a@example.test'));
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        $second = $this->placeOrder(self::orderBody($event, 'Bob', 'b@example.test'));
        self::assertResponseStatusCodeSame(201);
        $this->login(self::OWNER);

        $body = ['eventId' => $event['eventId'], 'name' => 'Test', 'subject' => 'Ahoj {{ recipient.name }}', 'body' => 'Zpráva', 'audience' => ['activityIds' => [$event['trail']]]];
        $result = $this->request('POST', self::BASE.'/campaigns/preview', $body);
        self::assertResponseStatusCodeSame(200, (string) $this->client->getResponse()->getContent());
        self::assertSame(2, $result['recipientCount'] ?? null, 'one orderer, one activity: two orders');
        self::assertSame('Ahoj Jana Nováková', $result['subject'] ?? null);

        $body['audience'] = ['activityIds' => [(string) $event['kids']]];
        self::assertSame(0, $this->request('POST', self::BASE.'/campaigns/preview', $body)['recipientCount'] ?? null, 'nobody registered for the children\'s race');

        // PRV-07: a marketing campaign reaches only people with a granted consent; nobody gave one here.
        $body['purpose'] = 'marketing';
        $body['audience'] = [];
        self::assertSame(0, $this->request('POST', self::BASE.'/campaigns/preview', $body)['recipientCount'] ?? null);

        // Narrowed by the payment state of Payments: cash orders are not paid yet.
        $body['purpose'] = 'organizational';
        $body['audience'] = ['financialStates' => ['paid']];
        self::assertSame(0, $this->request('POST', self::BASE.'/campaigns/preview', $body)['recipientCount'] ?? null);
        $body['audience'] = ['financialStates' => ['unpaid']];
        self::assertSame(2, $this->request('POST', self::BASE.'/campaigns/preview', $body)['recipientCount'] ?? null);

        // Narrowed by the check-in of CheckIn: nobody arrived.
        $body['audience'] = ['checkedIn' => true];
        self::assertSame(0, $this->request('POST', self::BASE.'/campaigns/preview', $body)['recipientCount'] ?? null);
        $body['audience'] = ['checkedIn' => false];
        self::assertSame(2, $this->request('POST', self::BASE.'/campaigns/preview', $body)['recipientCount'] ?? null);
        unset($second);
    }

    public function test_sending_a_campaign_plans_one_delivery_per_member_and_lists_them(): void
    {
        $event = $this->openEvent();
        $this->publishAndOpen($event['eventId']);
        $this->placeOrder(self::orderBody($event, 'Anna', 'a@example.test'));
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        $this->login(self::OWNER);

        $campaign = $this->request('POST', self::BASE.'/campaigns', ['eventId' => $event['eventId'], 'name' => 'Pro všechny', 'templateKey' => 'custom', 'audience' => []]);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        $id = Json::str($campaign ?? [], 'campaignId');

        $sent = $this->request('POST', self::BASE.'/campaigns/'.$id.'/send');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, Json::int($sent ?? [], 'recipients'));

        $history = $this->request('GET', self::BASE.'/campaigns/'.$id.'/deliveries');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, Json::int($history ?? [], 'total'));
        self::assertSame('scheduled', Json::str($history ?? [], 'items.0.state'));
        self::assertNull(Json::at($history ?? [], 'items.0.recipientHint'), 'no address is known before the first attempt');

        $detail = $this->request('GET', self::BASE.'/campaigns/'.$id);
        self::assertSame(['scheduled' => 1], Json::at($detail ?? [], 'deliveries'));
        self::assertSame('sending', Json::str($detail ?? [], 'state'));

        // a waiting delivery cannot be retried
        $this->request('POST', self::BASE.'/deliveries/'.Json::str($history ?? [], 'items.0.id').'/retry');
        $this->assertProblem(409, 'delivery_not_retryable');
    }
}
