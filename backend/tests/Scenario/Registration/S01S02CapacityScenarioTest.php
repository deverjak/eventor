<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario\Registration;

use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\Registration\RegistrationScenarioCase;

/**
 * S01 and S02 at the level of the order: the last seat goes to exactly one order, and an order over a full and a free
 * activity leaves nothing behind. The contention itself (real processes, real commits) is proven by
 * Integration/Registration/Capacity/CapacityRaceTest; here the whole PlaceOrder path is checked.
 */
final class S01S02CapacityScenarioTest extends RegistrationScenarioCase
{
    public function test_s01_two_people_register_for_the_last_seat(): void
    {
        $event = $this->openEvent(trailLimit: 1);
        $this->publishAndOpen($event['eventId']);

        $first = $this->placeOrder(self::orderBody($event, 'Jana', 'jana@example.test'));
        $firstStatus = $this->client->getResponse()->getStatusCode();
        $second = $this->placeOrder(self::orderBody($event, 'Petr', 'petr@example.test'));
        $secondStatus = $this->client->getResponse()->getStatusCode();

        self::assertSame([201, 409], [$firstStatus, $secondStatus]);
        self::assertIsArray($first);
        self::assertSame('capacity_exhausted', $second['code'] ?? null);
        self::assertSame(1, $this->bucketUse($event['trailVariant']));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM registration.participation WHERE event_id = ?', [$event['eventId']]));
        $this->drain();
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'order.placed' AND organizer_id = ?", [$this->owner['organizerId']]));
    }

    public function test_s02_an_order_with_a_full_and_a_free_activity_is_all_or_nothing(): void
    {
        $event = $this->openEvent(trailLimit: 1, kids: true);
        $this->publishAndOpen($event['eventId']);
        $this->placeOrder(self::orderBody($event));

        $this->placeOrder(self::orderBody($event, 'Petr', 'petr@example.test', overrides: [
            'items' => [
                ['personRef' => 'k', 'activityId' => (string) $event['kids'], 'variantId' => (string) $event['kidsVariant']],
                ['personRef' => 'p', 'activityId' => $event['trail'], 'variantId' => $event['trailVariant']],
            ],
            'persons' => [
                ['personRef' => 'k', 'answers' => ['first_name' => 'Kuba', 'last_name' => 'Novák', 'birth_date' => '2018-01-02', 'guardian_first_name' => 'Karel', 'guardian_last_name' => 'Novák', 'guardian_email' => 'karel@example.test']],
                ['personRef' => 'p', 'answers' => ['first_name' => 'Petr', 'last_name' => 'Novák', 'birth_date' => '1985-01-02']],
            ],
        ]));

        $this->assertProblem(409, 'capacity_exhausted');
        self::assertSame(0, $this->bucketUse((string) $event['kidsVariant']));
        self::assertSame(1, $this->dbInt("SELECT confirmed FROM registration.capacity_bucket WHERE scope_type = 'event' AND scope_id = ?", [$event['eventId']]));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM registration.orders WHERE event_id = ?', [$event['eventId']]));
        $placed = Json::at($this->request('GET', self::PUBLIC.'vikend/availability') ?? [], 'shown');
        self::assertTrue($placed);
    }
}
