<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\CheckIn;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Registration\Contract\ParticipationQrTokens;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\QueueDrainer;
use Eventor\Tests\Support\Registration\RegistrationScenarioCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Base of the CheckIn HTTP integration tests: the organizer "klub" with a published event that has two activities (the
 * trail and the children's race), the check-in lists provisioned from `catalog.event_published`, participants who order
 * through the public endpoint (a cash order: confirmed and unpaid) and helpers for both ways into the check-in API, the
 * team session and the device token.
 */
abstract class CheckInHttpTestCase extends RegistrationScenarioCase
{
    protected const int AMOUNT_DUE = 30000;

    /** @var array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string} */
    protected array $event;

    protected string $trailList;

    protected string $kidsList;

    private int $participants = 0;

    protected function openDesk(): void
    {
        $this->event = $this->openEvent(kids: true);
        // The in-memory transports are reset after every request: deliver catalog.event_published before the next one.
        $this->publish($this->event['eventId']);
        $this->drain();
        $this->request('POST', self::BASE.'/events/'.$this->event['eventId'].'/registration/open', ['cascade' => true]);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        $this->drain();
        $this->trailList = $this->listOf($this->event['trail']);
        \assert(null !== $this->event['kids']);
        $this->kidsList = $this->listOf($this->event['kids']);
    }

    protected function listOf(string $activityId): string
    {
        return $this->dbString('SELECT id FROM checkin.check_in_list WHERE activity_id = ?', [$activityId]);
    }

    /** The team route of an action of the desk. */
    protected function teamPath(string $suffix = '', ?string $slug = null, ?string $eventId = null): string
    {
        return '/api/check-in/org/'.($slug ?? 'klub').'/events/'.($eventId ?? $this->event['eventId']).$suffix;
    }

    /** The device route of an action of the desk (no organizer slug). */
    protected function devicePath(string $suffix = '', ?string $eventId = null): string
    {
        return '/api/check-in/events/'.($eventId ?? $this->event['eventId']).$suffix;
    }

    /**
     * A participant who ordered the trail with cash: confirmed, unpaid.
     *
     * @return array{orderId: string, participationId: string, code: string}
     */
    protected function participant(string $first = 'Jana'): array
    {
        ++$this->participants;
        $email = 'jana'.$this->participants.'@example.test';
        $placed = $this->placeOrder(self::orderBody($this->event, $first, $email));
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        $orderId = Json::str($placed, 'orderId');

        $participant = [
            'orderId' => $orderId,
            'participationId' => $this->dbString('SELECT id FROM registration.participation WHERE order_id = ?', [$orderId]),
            'code' => $this->dbString('SELECT registration_code FROM registration.participation WHERE order_id = ?', [$orderId]),
        ];
        $this->login(self::OWNER);

        return $participant;
    }

    /**
     * A participant whose order is paid in full, so that nothing stands between them and the door.
     *
     * @return array{orderId: string, participationId: string, code: string}
     */
    protected function paidParticipant(string $first = 'Petra'): array
    {
        $participant = $this->participant($first);
        $this->payCash($participant['orderId']);
        $this->login(self::OWNER);

        return $participant;
    }

    protected function payCash(string $orderId, int $amount = self::AMOUNT_DUE): void
    {
        $this->login(self::OWNER);
        $this->request('POST', self::BASE.'/orders/'.$orderId.'/cash', ['amountMinor' => $amount], ['Idempotency-Key' => $this->key()]);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        $this->client->getCookieJar()->clear();
    }

    protected function qrOf(string $participationId): string
    {
        return $this->service(ParticipationQrTokens::class)->issue(OrganizerId::fromString($this->owner['organizerId']), ParticipationId::fromString($participationId));
    }

    protected function key(): string
    {
        return bin2hex(random_bytes(12));
    }

    /**
     * A check-in call. Headers default to a fresh idempotency key; `$base` is the team or the device path of the list.
     *
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     *
     * @return array<array-key, mixed>|null
     */
    protected function post(string $path, array $body, array $headers = [], ?string $key = null): ?array
    {
        return $this->request('POST', $path, $body, $headers + ['Idempotency-Key' => $key ?? $this->key()]);
    }

    /**
     * @return array{id: string, token: string}
     */
    protected function issueDeviceToken(?string $listId = null, string $label = 'Gate A tablet'): array
    {
        $this->login(self::OWNER);
        $issued = $this->request('POST', self::BASE.'/device-tokens', ['label' => $label, 'checkInListId' => $listId]);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        $this->client->getCookieJar()->clear();

        return ['id' => Json::str($issued, 'id'), 'token' => Json::str($issued, 'token')];
    }

    /** @return array<string, string> */
    protected static function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    /** Switches the organizer policy "check-in may record cash". */
    protected function allowDevicesToTakeCash(bool $allowed = true): void
    {
        $this->login(self::OWNER);
        $this->request('PUT', self::BASE.'/payment-settings', ['defaultDueDays' => 14, 'defaultHoldTtlHours' => 48, 'autoRestoreLatePayment' => false, 'overpaymentHandling' => 'ask', 'checkInMayRecordCash' => $allowed]);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        $this->client->getCookieJar()->clear();
    }

    /** Signs in a new member of "klub" with the given role. */
    protected function signInAs(Role $role, string $email): void
    {
        $this->addMember($email, $role);
        $this->client->getCookieJar()->clear();
        $this->login($email);
    }

    /** Signs in the owner of another organizer ("other-club"), a foreign tenant. */
    protected function signInAsForeignOwner(): void
    {
        $this->createOwner('stranger@example.test', 'other-club', 'Other Club');
        $this->client->getCookieJar()->clear();
        $this->login('stranger@example.test');
    }

    protected function signInAsOwner(): void
    {
        $this->client->getCookieJar()->clear();
        $this->login(self::OWNER);
    }

    protected function setParticipationColumn(string $participationId, string $column, string $value): void
    {
        $this->connection()->executeStatement(\sprintf('UPDATE registration.participation SET %s = ? WHERE id = ?', $column), [$value, $participationId]);
    }

    protected function validCheckIns(string $participationId): int
    {
        return $this->dbInt('SELECT count(*) FROM checkin.check_in WHERE participation_id = ? AND voided_at IS NULL', [$participationId]);
    }

    /** Delivers what is queued for CheckIn only (other modules' reactions to the same event stay queued). */
    protected function drainCheckIn(): void
    {
        $drainer = static::getContainer()->get(QueueDrainer::class);
        \assert($drainer instanceof QueueDrainer);
        $result = $drainer->drain(['checkin_events']);
        self::assertSame(0, $result->failed, 'a CheckIn subscriber failed');
    }

    /**
     * Puts an integration event on the event bus the way a publisher does; the outbox routes it to the subscribers.
     */
    protected function publishIntegrationEvent(object $event): void
    {
        $bus = static::getContainer()->get('test.event_bus');
        \assert($bus instanceof MessageBusInterface);
        $bus->dispatch($event, [TenantStamp::of(OrganizerId::fromString($this->owner['organizerId']))]);
    }
}
