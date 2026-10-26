<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Communication;

use Eventor\Communication\Application\Delivery\AttemptDeliveryHandler;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\CampaignState;
use Eventor\Communication\Contract\DeliveryState;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Campaign\Campaign;
use Eventor\Communication\Domain\Delivery\Delivery;
use Eventor\Communication\Domain\Event\CampaignChanged;
use Eventor\Communication\Domain\Event\DeliveryFailedPermanently;
use Eventor\Communication\Domain\Exception\CampaignStateInvalid;
use Eventor\Communication\Domain\Exception\DeliveryNotRetryable;
use Eventor\Communication\Domain\Exception\InvalidCampaign;
use Eventor\Communication\Domain\Exception\InvalidReminderSettings;
use Eventor\Communication\Domain\Reminder\ReminderSettings;
use Eventor\Communication\Infrastructure\Mail\MailerMessageSender;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The rules of the Communication aggregates without a kernel: the delivery and campaign state machines (the same
 * definition as in services.php), the attempt budget, retry, validation.
 */
final class CommunicationDomainTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-01T12:00:00+00:00');
    }

    public function test_a_delivery_is_sent_once_and_then_final(): void
    {
        $machine = self::deliveryMachine();
        $delivery = $this->delivery();

        $delivery->markSent($machine, '<id@x>', 'j***@example.test', $this->now);

        self::assertSame(DeliveryState::Sent, $delivery->getState());
        self::assertSame(1, $delivery->attempts());
        $this->expectException(\Symfony\Component\Workflow\Exception\NotEnabledTransitionException::class);
        $delivery->skip($machine, 'too_late', $this->now);
    }

    public function test_five_failed_attempts_exhaust_the_budget_and_the_failure_is_recorded(): void
    {
        $delivery = $this->delivery();

        for ($i = 1; $i < Delivery::MAX_ATTEMPTS; ++$i) {
            self::assertFalse($delivery->recordFailedAttempt('smtp_421', $this->now));
        }
        self::assertTrue($delivery->recordFailedAttempt('smtp_421', $this->now));
        $delivery->fail(self::deliveryMachine(), 'smtp_421', $this->now);

        self::assertSame(DeliveryState::Failed, $delivery->getState());
        $events = $delivery->releaseEvents();
        self::assertInstanceOf(DeliveryFailedPermanently::class, end($events));
    }

    public function test_only_a_failed_delivery_can_be_retried_and_it_gets_a_fresh_budget(): void
    {
        $machine = self::deliveryMachine();
        $delivery = $this->delivery();

        try {
            $delivery->retry($machine, $this->now);
            self::fail('A waiting delivery is not retryable.');
        } catch (DeliveryNotRetryable) {
            $this->addToAssertionCount(1);
        }

        $delivery->fail($machine, 'transport_error', $this->now);
        $delivery->retry($machine, $this->now);

        self::assertSame(DeliveryState::Scheduled, $delivery->getState());
        self::assertSame(0, $delivery->attempts());
    }

    public function test_a_waiting_delivery_follows_a_new_time_but_a_sent_one_does_not(): void
    {
        $machine = self::deliveryMachine();
        $waiting = $this->delivery();
        $later = $this->now->modify('+2 days');
        $waiting->replan($later, $this->now);
        self::assertEquals($later, $waiting->sendAfter());

        $sent = $this->delivery();
        $sent->markSent($machine, null, 'a***@x.test', $this->now);
        $sent->replan($later, $this->now);
        self::assertEquals($this->now, $sent->sendAfter());
    }

    public function test_a_campaign_goes_draft_scheduled_sending_sent_and_a_sent_one_cannot_be_edited(): void
    {
        $machine = self::campaignMachine();
        $campaign = $this->campaign();

        $campaign->schedule($machine, $this->now->modify('+1 day'), $this->now);
        self::assertSame(CampaignState::Scheduled, $campaign->getState());
        $campaign->start($machine, $this->now);
        $campaign->complete($machine, 3, 1, $this->now);
        self::assertSame(CampaignState::Sent, $campaign->getState());

        $this->expectException(CampaignStateInvalid::class);
        $campaign->edit('x', CampaignPurpose::Organizational, null, MessageLocale::Czech, 'S', 'B', new CampaignAudienceSpec(), $this->now);
    }

    public function test_a_campaign_cannot_be_scheduled_in_the_past_or_cancelled_once_sending(): void
    {
        $machine = self::campaignMachine();
        $campaign = $this->campaign();

        try {
            $campaign->schedule($machine, $this->now->modify('-1 minute'), $this->now);
            self::fail('The past is refused.');
        } catch (InvalidCampaign) {
            $this->addToAssertionCount(1);
        }

        $campaign->start($machine, $this->now);
        $this->expectException(CampaignStateInvalid::class);
        $campaign->cancel($machine, $this->now);
    }

    public function test_a_campaign_needs_a_name_a_subject_and_a_body(): void
    {
        $this->expectException(InvalidCampaign::class);

        Campaign::draft(CampaignId::generate(), OrganizerId::generate(), EventId::generate(), ' ', CampaignPurpose::Organizational, null, MessageLocale::Czech, 'S', 'B', new CampaignAudienceSpec(), $this->now);
    }

    public function test_a_new_campaign_records_its_draft(): void
    {
        $events = $this->campaign()->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(CampaignChanged::class, $events[0]);
    }

    public function test_the_audience_survives_json(): void
    {
        $spec = new CampaignAudienceSpec(['a'], ['v'], ['confirmed'], ['unpaid'], true);

        self::assertEquals($spec, CampaignAudienceSpec::fromArray($spec->toArray()));
        self::assertEquals(new CampaignAudienceSpec(), CampaignAudienceSpec::fromArray([]));
    }

    public function test_reminder_settings_are_sorted_bounded_and_unique(): void
    {
        $settings = ReminderSettings::create(OrganizerId::generate(), [-24, -72, -24, 48], 12, $this->now);
        self::assertSame([-72, -24, 48], $settings->paymentOffsetsHours());

        foreach ([[[-1000], 24], [[1, 2, 3, 4, 5, 6], 24], [[-24], 0], [[-24], 400]] as [$offsets, $pre]) {
            try {
                ReminderSettings::create(OrganizerId::generate(), $offsets, $pre, $this->now);
                self::fail('Refused.');
            } catch (InvalidReminderSettings) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_recipient_hint_hides_the_address(): void
    {
        self::assertSame('j***@example.test', AttemptDeliveryHandler::mask('jana.novakova@example.test'));
        self::assertSame('***', AttemptDeliveryHandler::mask('not-an-address'));
    }

    public function test_the_html_part_is_the_escaped_text_with_links_and_the_qr(): void
    {
        $html = MailerMessageSender::html("a <b>bold</b>\nsee https://example.test/x?a=1&b=2", true);

        self::assertStringContainsString('a &lt;b&gt;bold&lt;/b&gt;<br />', $html);
        self::assertStringContainsString('<a href="https://example.test/x?a=1&amp;b=2">', $html);
        self::assertStringContainsString('cid:payment-qr', $html);
        self::assertStringNotContainsString('cid:', MailerMessageSender::html('x', false));
    }

    private function delivery(): Delivery
    {
        return Delivery::schedule(DeliveryId::generate(), OrganizerId::generate(), null, 'order_received', TemplateKey::OrderReceived, CampaignPurpose::Organizational, null, OrderId::generate(), null, null, [], 'order_received:'.bin2hex(random_bytes(4)), $this->now, $this->now);
    }

    private function campaign(): Campaign
    {
        return Campaign::draft(CampaignId::generate(), OrganizerId::generate(), EventId::generate(), 'Změna', CampaignPurpose::Organizational, null, MessageLocale::Czech, 'Předmět', 'Tělo', new CampaignAudienceSpec(), $this->now);
    }

    private static function deliveryMachine(): WorkflowInterface
    {
        return new StateMachine(
            new Definition(['scheduled', 'sent', 'delivered', 'skipped', 'failed'], [
                new Transition('send', 'scheduled', 'sent'),
                new Transition('skip', 'scheduled', 'skipped'),
                new Transition('deliver', 'sent', 'delivered'),
                new Transition('fail', 'scheduled', 'failed'),
                new Transition('fail', 'sent', 'failed'),
                new Transition('retry', 'failed', 'scheduled'),
            ], 'scheduled'),
            new MethodMarkingStore(true, 'state'),
            null,
            'delivery',
        );
    }

    private static function campaignMachine(): WorkflowInterface
    {
        return new StateMachine(
            new Definition(['draft', 'scheduled', 'sending', 'sent', 'cancelled'], [
                new Transition('schedule', 'draft', 'scheduled'),
                new Transition('schedule', 'scheduled', 'scheduled'),
                new Transition('start', 'draft', 'sending'),
                new Transition('start', 'scheduled', 'sending'),
                new Transition('complete', 'sending', 'sent'),
                new Transition('cancel', 'draft', 'cancelled'),
                new Transition('cancel', 'scheduled', 'cancelled'),
            ], 'draft'),
            new MethodMarkingStore(true, 'state'),
            null,
            'campaign',
        );
    }
}
