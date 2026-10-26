<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario\Communication;

use Eventor\Communication\Infrastructure\Mail\MailerMessageSender;
use Eventor\Tests\Support\Catalog\Json;

/**
 * REG-07 and PAY-09 end to end against the real Registration, Payments and the in-memory Pairrot: the confirmation of a
 * transfer order carries the payment details and the QR code, the reminders follow the schedule, and a reminder never
 * follows a payment or a cancellation (COM-02, the negative test of F4).
 */
final class PaymentMessagesTest extends CommunicationScenarioCase
{
    /** @var array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string} */
    private array $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connectPairrot();
        $this->event = $this->openEvent(methods: ['bank_transfer']);
        $this->publishAndOpen($this->event['eventId']);
    }

    /**
     * @return array{orderId: string, participationId: string, placed: array<array-key, mixed>}
     */
    private function transferOrder(string $email): array
    {
        $order = $this->orderFor($this->event, $email, 'bank_transfer');
        $this->drainWithMailRetries();

        return $order;
    }

    private function isReminder(object $mail): bool
    {
        return $mail instanceof \Symfony\Component\Mime\Email && str_starts_with((string) $mail->getSubject(), 'Připomínka platby');
    }

    public function test_the_confirmation_carries_the_payment_details_and_the_qr_code(): void
    {
        $order = $this->transferOrder('anna@example.test');

        $mails = $this->mailer()->emailsTo('anna@example.test');
        self::assertCount(1, $mails, 'one message confirms a transfer order: the payment details, not an "order received" first');
        $mail = $mails[0];
        $row = $this->dbRow('SELECT variable_symbol FROM payments.payment_request WHERE order_id = ?', [$order['orderId']]);
        $number = $this->dbString('SELECT number FROM registration.orders WHERE id = ?', [$order['orderId']]);
        $text = (string) $mail->getTextBody();

        self::assertSame('Registrace '.$number.' přijata – platební údaje', $mail->getSubject());
        self::assertStringContainsString(Json::str($row, 'variable_symbol'), $text);
        self::assertStringContainsString($number, $text);
        self::assertStringContainsString('/s/ss1_', $text, 'REG-07: the link to the self-service');
        self::assertStringContainsString('Anna', $text);
        self::assertStringContainsString('cid:'.MailerMessageSender::QR_CONTENT_ID, (string) $mail->getHtmlBody());
        $png = array_filter($mail->getAttachments(), static fn($part): bool => 'image' === $part->getMediaType() && 'png' === $part->getMediaSubtype());
        self::assertCount(1, $png, 'the SPAYD QR code is attached inline');
        self::assertSame('Klub Plzeň přes Eventor', $mail->getFrom()[0]->getName());
    }

    public function test_reminders_follow_the_schedule_and_a_paid_order_gets_none(): void
    {
        $unpaid = $this->transferOrder('unpaid@example.test');
        $paid = $this->transferOrder('paid@example.test');
        $this->payByTransfer($paid['orderId'], 30_000);
        $this->drainWithMailRetries();
        self::assertSame('paid', $this->dbString('SELECT state FROM payments.order_finance WHERE order_id = ?', [$paid['orderId']]));
        self::assertSame(2, $this->dbInt("SELECT count(*) FROM communication.delivery WHERE trigger_key = 'payment_reminder' AND state = 'scheduled'"), 'one 24-hour reminder per request fits the current payment-request TTL');

        $due = new \DateTimeImmutable($this->dbString('SELECT due_at FROM payments.payment_request WHERE order_id = ?', [$unpaid['orderId']]));

        // The active 24-hour reminder is due. The worker checks again and writes only to the order that is still unpaid.
        self::mockTime($due->modify('-12 hours'));
        $this->sweep();
        $this->drainWithMailRetries();
        self::assertCount(1, array_filter($this->mailer()->emailsTo('unpaid@example.test'), $this->isReminder(...)));
        self::assertCount(0, array_filter($this->mailer()->emailsTo('paid@example.test'), $this->isReminder(...)), 'never after the payment');
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM communication.delivery WHERE order_id = ? AND trigger_key = 'payment_reminder' AND state = 'skipped' AND last_error = 'already_paid'", [$paid['orderId']]));

        self::assertSame(1, $this->dbInt("SELECT count(*) FROM communication.delivery WHERE order_id = ? AND trigger_key = 'payment_reminder' AND state = 'skipped'", [$paid['orderId']]));
    }

    public function test_a_reminder_does_not_follow_a_cancellation(): void
    {
        $order = $this->transferOrder('cancelled@example.test');
        $auth = self::selfService($order['placed']);
        $this->request('POST', '/api/self-service/participations/'.$order['participationId'].'/cancel', ['reason' => 'Nemoc'], $auth);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        $this->drainWithMailRetries();

        $due = new \DateTimeImmutable($this->dbString('SELECT due_at FROM payments.payment_request WHERE order_id = ?', [$order['orderId']]));
        self::mockTime($due->modify('-12 hours'));
        $this->sweep();
        $this->drainWithMailRetries();

        self::assertCount(0, array_filter($this->mailer()->emailsTo('cancelled@example.test'), $this->isReminder(...)));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM communication.delivery WHERE order_id = ? AND trigger_key = 'payment_reminder' AND state = 'skipped' AND last_error IN ('no_live_participation', 'already_paid', 'no_finance', 'request_not_active')", [$order['orderId']]));
    }

    public function test_the_payment_confirmation_and_the_pre_event_reminder(): void
    {
        $order = $this->transferOrder('anna@example.test');
        $this->mailer()->reset();
        $this->payByTransfer($order['orderId'], 30_000);
        $this->drainWithMailRetries();

        $subjects = array_map(static fn($mail): string => (string) $mail->getSubject(), $this->mailer()->emailsTo('anna@example.test'));
        self::assertSame(['Účast potvrzena – Borský podzimní víkend 2026'], $subjects, 'one message for a full payment');

        // 24 h before the trail starts (Saturday 21 Nov 2026 10:00 Prague) the reminder is queued and sent.
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM communication.delivery WHERE order_id = ? AND trigger_key = 'pre_event_reminder'", [$order['orderId']]));
        self::mockTime('2026-11-20 09:30:00 UTC');
        $this->sweep();
        $this->drainWithMailRetries();
        $subjects = array_map(static fn($mail): string => (string) $mail->getSubject(), $this->mailer()->emailsTo('anna@example.test'));
        self::assertContains('Brzy začínáme – Trail 5 km', $subjects);
        self::assertSame(1, Json::int(['n' => $this->dbInt("SELECT count(*) FROM communication.delivery WHERE order_id = ? AND trigger_key = 'pre_event_reminder' AND state = 'sent'", [$order['orderId']])], 'n'));
    }
}
