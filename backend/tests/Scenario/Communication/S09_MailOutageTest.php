<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario\Communication;

use Eventor\Communication\Application\Delivery\RetryFailedDeliveries;
use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Exception\DeliveryNotRetryable;
use Eventor\Communication\Infrastructure\Mail\MailerMessageSender;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;

/**
 * S09, COM-06, COM-07: the mail service is down. Registration is not affected (the order is placed, 201), the message
 * waits in the queue, it is sent exactly once after the service is back; when the retries run out the delivery fails,
 * Attention hears about it, and "retry the failed" sends it once without a duplicate.
 */
final class S09_MailOutageTest extends CommunicationScenarioCase
{
    /** @var array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string} */
    private array $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = $this->openEvent();
        $this->publishAndOpen($this->event['eventId']);
    }

    public function test_a_short_outage_delays_the_message_and_it_is_sent_once(): void
    {
        $this->mailer()->failNext(2);
        $order = $this->orderFor($this->event, 'anna@example.test');

        $this->drainWithMailRetries();

        self::assertSame('confirmed', $this->participationState($order['participationId']), 'the registration did not depend on the mail');
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM communication.delivery WHERE order_id = ? AND state = 'sent'", [$order['orderId']]));
        self::assertSame(3, $this->dbInt('SELECT attempts FROM communication.delivery WHERE order_id = ?', [$order['orderId']]), 'two refused attempts and the one that went through');
        self::assertCount(1, $this->mailer()->emailsTo('anna@example.test'));

        $mail = $this->mailer()->emailsTo('anna@example.test')[0];
        self::assertSame($this->dbString('SELECT id FROM communication.delivery WHERE order_id = ?', [$order['orderId']]), $mail->getHeaders()->get(MailerMessageSender::DELIVERY_HEADER)?->getBodyAsString());
        self::assertStringContainsString($this->dbString('SELECT number FROM registration.orders WHERE id = ?', [$order['orderId']]), (string) $mail->getSubject());
        self::assertStringContainsString('/s/ss1_', (string) $mail->getTextBody(), 'REG-07: the link to the self-service');
    }

    public function test_a_long_outage_fails_the_delivery_and_the_retry_sends_it_exactly_once(): void
    {
        $this->mailer()->outage();
        $order = $this->orderFor($this->event, 'anna@example.test');

        $this->drainWithMailRetries();

        $row = $this->dbRow('SELECT id, state, attempts, last_error FROM communication.delivery WHERE order_id = ?', [$order['orderId']]);
        self::assertSame('failed', $row['state']);
        self::assertSame(5, $row['attempts']);
        self::assertSame('transport_error', $row['last_error'], 'a code, not the address');
        self::assertCount(0, $this->mailer()->emails());
        // Communication tells Attention (communication.delivery_failed), which opens one item.
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE type = 'delivery_failed'"));

        $this->mailer()->outage(false);
        $deliveryId = $row['id'];
        \assert(\is_string($deliveryId));
        self::assertSame(['retried' => 1], $this->commandBus()->dispatch(new RetryFailedDeliveries(deliveryId: $deliveryId), TenantStamp::of($this->organizer())));
        $this->drainWithMailRetries();

        self::assertCount(1, $this->mailer()->emailsTo('anna@example.test'));
        self::assertSame('sent', $this->dbString('SELECT state FROM communication.delivery WHERE id = ?', [$deliveryId]));

        $this->expectException(DeliveryNotRetryable::class);
        $this->commandBus()->dispatch(new RetryFailedDeliveries(deliveryId: $deliveryId), TenantStamp::of($this->organizer()));
    }

    public function test_the_same_fact_scheduled_twice_makes_one_delivery(): void
    {
        $order = $this->orderFor($this->event, 'anna@example.test');
        $this->drainWithMailRetries();

        $again = $this->commandBus()->dispatch(new ScheduleDelivery(TemplateKey::OrderReceived, 'order_received:'.$order['orderId'], orderId: $order['orderId']), TenantStamp::of($this->organizer()));
        $this->drainWithMailRetries();

        self::assertSame(['deliveryId' => null], $again);
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM communication.delivery WHERE order_id = ?', [$order['orderId']]));
        self::assertCount(1, $this->mailer()->emailsTo('anna@example.test'));
    }
}
