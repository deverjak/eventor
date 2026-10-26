<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario\Registration;

use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\Registration\RegistrationScenarioCase;

/**
 * Reservation expiry (CAP-04) and the payment paths through Payments (S04, S05, S06, CAP-05): a bank-transfer order
 * holds the seat, a signed Pairrot webhook confirms it, a late one lands on the exception queue or restores it.
 */
final class ReservationLifecycleScenarioTest extends RegistrationScenarioCase
{
    /**
     * @return array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string}
     */
    private function transferEvent(bool $autoRestore = false, int $limit = 1): array
    {
        $this->connectPairrot();
        $event = $this->openEvent(trailLimit: $limit, methods: ['bank_transfer']);
        $this->request('PUT', self::BASE.'/events/'.$event['eventId'].'/payment-rules', ['methods' => ['bank_transfer'], 'dueDays' => 7, 'holdTtlHours' => 48, 'autoRestoreOnLatePayment' => $autoRestore]);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        $this->publishAndOpen($event['eventId']);

        return $event;
    }

    /**
     * @param array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string} $event
     *
     * @return array{0: string, 1: string} order id, participation id
     */
    private function transferOrder(array $event, string $first = 'Jana', string $email = 'jana@example.test'): array
    {
        $placed = $this->placeOrder(self::orderBody($event, $first, $email, overrides: ['paymentMethod' => 'bank_transfer']));
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertSame('instructions_pending', Json::at($placed ?? [], 'payment.status'));
        $this->drain();

        $order = Json::str($placed ?? [], 'orderId');

        return [$order, $this->dbString('SELECT id FROM registration.participation WHERE order_id = ?', [$order])];
    }

    public function test_a_transfer_order_holds_the_seat_and_the_payment_request_follows(): void
    {
        $event = $this->transferEvent();

        [$order, $participation] = $this->transferOrder($event);

        self::assertSame('pending', $this->participationState($participation));
        self::assertSame(1, $this->heldSeats($event['trailVariant']));
        self::assertNotNull($this->dbRow('SELECT hold_expires_at FROM registration.participation WHERE id = ?', [$participation])['hold_expires_at']);
        self::assertSame('issued', $this->dbString('SELECT status FROM payments.payment_request WHERE order_id = ?', [$order]));
    }

    public function test_the_reservation_expires_and_the_seat_goes_back_without_touching_the_payment_request(): void
    {
        $event = $this->transferEvent();
        [$order, $participation] = $this->transferOrder($event);

        self::mockTime('+47 hours');
        $this->sweepReservations();
        self::assertSame('pending', $this->participationState($participation), 'the reservation is still valid');

        self::mockTime('+2 hours');
        $this->sweepReservations();
        $this->drain();

        self::assertSame('expired', $this->participationState($participation));
        self::assertSame(0, $this->bucketUse($event['trailVariant']));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'participation.state.expire' AND entity_id = ? AND actor_type = 'SYSTEM'", [$participation]));
        self::assertSame(30000, $this->dbInt('SELECT amount_due_minor FROM registration.orders WHERE id = ?', [$order]), 'expiry does not change what is owed');
        $request = $this->pairrot()->requestByExternalId('order_'.$order.'_1');
        self::assertNotNull($request);
        self::assertFalse($request['cancelled'], 'a late payment must still match (chapter 04, path 4)');

        // the seat is free for somebody else, and a second sweep changes nothing
        $this->placeOrder(self::orderBody($event, 'Petr', 'petr@example.test', overrides: ['paymentMethod' => 'bank_transfer']));
        self::assertResponseStatusCodeSame(201);
        $this->sweepReservations();
        self::assertSame('expired', $this->participationState($participation));
    }

    public function test_the_payment_confirms_the_participation_and_a_partial_one_keeps_the_reservation_s04(): void
    {
        $event = $this->transferEvent(limit: 5);
        [$order, $participation] = $this->transferOrder($event);

        $this->payByTransfer($order, 10000);
        $this->drain();
        self::assertSame('pending', $this->participationState($participation), 'a partial payment keeps the reservation');
        self::assertSame(1, $this->heldSeats($event['trailVariant']));

        $this->payByTransfer($order, 20000);
        $this->drain();

        self::assertSame('confirmed', $this->participationState($participation));
        self::assertSame(0, $this->heldSeats($event['trailVariant']));
        self::assertSame(1, $this->bucketUse($event['trailVariant']));
        self::assertNull($this->dbRow('SELECT hold_expires_at FROM registration.participation WHERE id = ?', [$participation])['hold_expires_at']);
    }

    public function test_s05_a_payment_after_expiry_with_a_full_capacity_needs_a_human_and_restoring_waits_for_room(): void
    {
        $event = $this->transferEvent(autoRestore: true);
        [$order, $participation] = $this->transferOrder($event);
        self::mockTime('+3 days');
        $this->sweepReservations();
        [, $taker] = $this->transferOrder($event, 'Petr', 'petr@example.test'); // the seat is taken again

        // An expired participation has claims for the event, activity and variant.  The new order fills all three;
        // a failed automatic reclaim must therefore leave every counter exactly as it was and flag the late payer.
        $beforeRestore = $this->dbRows(
            'SELECT scope_type, held, confirmed FROM registration.capacity_bucket WHERE event_id = ? ORDER BY scope_type, id',
            [$event['eventId']],
        );

        $this->payByTransfer($order, 30000);
        $this->drain();

        self::assertSame('requires_attention', $this->participationState($participation), 'no room: no automatic restore, whatever the setting says');
        self::assertSame($beforeRestore, $this->dbRows(
            'SELECT scope_type, held, confirmed FROM registration.capacity_bucket WHERE event_id = ? ORDER BY scope_type, id',
            [$event['eventId']],
        ));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE subject_id = ? AND type = 'late_payment'", [$participation]));

        $this->login(self::OWNER);
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/participations/'.$participation.'/restore', ['reason' => 'Zaplaceno']);
        $problem = $this->assertProblem(409, 'participation_transition_not_allowed');
        self::assertStringContainsString('capacity_exhausted', Json::str($problem, 'context.blockers'));

        // somebody cancels, the finance person restores
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/participations/'.$taker.'/cancel', ['reason' => 'Odhlášen']);
        self::assertResponseIsSuccessful();
        $restored = $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/participations/'.$participation.'/restore', ['reason' => 'Zaplaceno']);

        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertSame('confirmed', Json::str($restored ?? [], 'state'));
        self::assertSame(1, $this->dbInt("SELECT confirmed FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]));
        $this->drain();
        self::assertSame(0, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE subject_id = ? AND type = 'late_payment' AND state NOT IN ('resolved', 'dismissed')", [$participation]), 'the item closes on registration.participation_restored');
    }

    public function test_s06_a_payment_after_expiry_with_room_and_auto_restore_restores_as_the_system(): void
    {
        $event = $this->transferEvent(autoRestore: true, limit: 5);
        [$order, $participation] = $this->transferOrder($event);
        self::mockTime('+3 days');
        $this->sweepReservations();
        self::assertSame('expired', $this->participationState($participation));

        $this->payByTransfer($order, 30000);
        $this->drain();

        self::assertSame('confirmed', $this->participationState($participation));
        self::assertSame(1, $this->bucketUse($event['trailVariant']));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'participation.state.restore' AND entity_id = ? AND actor_type = 'SYSTEM'", [$participation]));
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM attention.attention_item WHERE subject_id = ?', [$participation]));
    }

    public function test_s05_without_auto_restore_a_late_payment_is_flagged_even_with_room(): void
    {
        $event = $this->transferEvent(autoRestore: false, limit: 5);
        [$order, $participation] = $this->transferOrder($event);
        self::mockTime('+3 days');
        $this->sweepReservations();

        $this->payByTransfer($order, 30000);
        $this->drain();

        self::assertSame('requires_attention', $this->participationState($participation));
        $this->login(self::OWNER);
        $restored = $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/participations/'.$participation.'/restore', ['reason' => 'Ruční obnova']);
        self::assertSame('confirmed', Json::str($restored ?? [], 'state'));
    }
}
