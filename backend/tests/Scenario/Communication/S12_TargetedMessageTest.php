<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario\Communication;

use Eventor\Communication\Application\Campaign\StartCampaign;
use Eventor\Communication\Application\Delivery\AttemptDelivery;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Tests\Support\Catalog\Json;

/**
 * S12, COM-02, COM-04, chapter 04 path 10: the children's race moves. Only the people of that activity are written to
 * (one message per orderer and activity), the Saturday runners get nothing, and somebody who cancels between the
 * decision and the send is skipped, not written to. Real Registration, Catalog and Payments.
 */
final class S12_TargetedMessageTest extends CommunicationScenarioCase
{
    /** @var array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string} */
    private array $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = $this->openEvent(kids: true);
        $this->publishAndOpen($this->event['eventId']);
    }

    /**
     * @param list<string> $children
     *
     * @return array{orderId: string, placed: array<array-key, mixed>}
     */
    private function familyOrder(string $email, array $children): array
    {
        $items = [];
        $persons = [];
        foreach ($children as $i => $name) {
            $items[] = ['personRef' => 'c'.$i, 'activityId' => (string) $this->event['kids'], 'variantId' => (string) $this->event['kidsVariant']];
            $persons[] = ['personRef' => 'c'.$i, 'answers' => ['first_name' => $name, 'last_name' => 'Novák', 'birth_date' => '2018-02-03', 'guardian_first_name' => 'Karel', 'guardian_last_name' => 'Novák', 'guardian_email' => $email]];
        }
        $placed = $this->placeOrder(['items' => $items, 'persons' => $persons, 'orderAnswers' => ['orderer_first_name' => 'Karel', 'orderer_last_name' => 'Novák', 'orderer_email' => $email], 'paymentMethod' => 'cash'], slug: $this->event['slug']);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        \assert(\is_array($placed) && \is_string($placed['orderId']));

        return ['orderId' => $placed['orderId'], 'placed' => $placed];
    }

    /**
     * @param array<array-key, mixed> $placed
     */
    private function cancelBySelfService(array $placed): void
    {
        $auth = self::selfService($placed);
        $order = $this->request('GET', '/api/self-service/order', null, $auth);
        $this->request('POST', '/api/self-service/participations/'.Json::str(Json::at($order ?? [], 'participations.0'), 'id').'/cancel', ['reason' => 'Nemoc'], $auth);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
    }

    public function test_only_the_affected_activity_is_notified_and_a_late_cancellation_is_skipped(): void
    {
        $this->familyOrder('rodic1@example.test', ['Anna', 'Boris']);
        $this->orderFor($this->event, 'bezec@example.test');
        $late = $this->familyOrder('pozde@example.test', ['Cyril']);
        $early = $this->familyOrder('drive@example.test', ['Dana']);
        $this->cancelBySelfService($early['placed']);
        $this->drainStrict();
        $this->mailer()->reset();

        // The organizer decides to notify the children's activity.
        $this->login(self::OWNER);
        $created = $this->request('POST', self::BASE.'/campaigns', ['eventId' => $this->event['eventId'], 'name' => 'Změna času dětského závodu', 'templateKey' => 'activity_changed', 'audience' => ['activityIds' => [(string) $this->event['kids']]]]);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        $campaignId = Json::str($created ?? [], 'campaignId');
        $preview = $this->request('POST', self::BASE.'/campaigns/preview', ['eventId' => $this->event['eventId'], 'name' => 'x', 'templateKey' => 'activity_changed', 'audience' => ['activityIds' => [(string) $this->event['kids']]]]);
        self::assertSame(2, Json::int($preview ?? [], 'recipientCount'), 'two orderers: the family (once, although two children) and the one who has not cancelled yet');
        // Start in this process so the in-memory mail transport retains the queued attempts until the cancellation
        // event has been applied. The HTTP endpoint delegates to this same command (covered by the integration test).
        self::assertSame(['recipients' => 2], $this->commandBus()->dispatch(new StartCampaign($campaignId), TenantStamp::of($this->organizer())));

        // Before the queue runs, one of them cancels.
        $this->cancelBySelfService($late['placed']);
        $this->drainStrict(['registration_events', 'communication_events']);
        // Execute the already queued campaign attempts after Registration applied the cancellation, as the mail worker
        // does. This keeps the late-cancellation check at the actual send boundary.
        foreach ($this->dbRows('SELECT id FROM communication.delivery WHERE campaign_id = ? ORDER BY id', [$campaignId]) as $delivery) {
            \assert(\is_string($delivery['id']));
            $this->commandBus()->dispatch(new AttemptDelivery($delivery['id']), TenantStamp::of($this->organizer()));
        }
        $this->drainStrict();

        self::assertCount(1, array_filter($this->mailer()->emailsTo('rodic1@example.test'), static fn($m): bool => str_starts_with((string) $m->getSubject(), 'Změna termínu')));
        self::assertCount(0, array_filter($this->mailer()->emailsTo('bezec@example.test'), static fn($m): bool => str_starts_with((string) $m->getSubject(), 'Změna termínu')), 'the Saturday runners get nothing');
        self::assertCount(0, array_filter($this->mailer()->emailsTo('drive@example.test'), static fn($m): bool => str_starts_with((string) $m->getSubject(), 'Změna termínu')));
        self::assertCount(0, array_filter($this->mailer()->emailsTo('pozde@example.test'), static fn($m): bool => str_starts_with((string) $m->getSubject(), 'Změna termínu')), 'cancelled between the decision and the send');
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM communication.delivery WHERE campaign_id = ? AND state = 'skipped' AND last_error = 'not_in_audience'", [$campaignId]));

        $family = array_values(array_filter($this->mailer()->emailsTo('rodic1@example.test'), static fn($m): bool => str_starts_with((string) $m->getSubject(), 'Změna termínu')))[0];
        self::assertStringContainsString('Dětský závod', (string) $family->getTextBody());
        self::assertStringContainsString('neděle 22. listopadu 2026 10:00', (string) $family->getTextBody());

        $detail = $this->request('GET', self::BASE.'/campaigns/'.$campaignId);
        self::assertSame('sent', Json::str($detail ?? [], 'state'));
        self::assertSame(['sent' => 1, 'skipped' => 1], Json::at($detail ?? [], 'deliveries'));
        $history = $this->request('GET', self::BASE.'/campaigns/'.$campaignId.'/deliveries');
        self::assertSame(2, Json::int($history ?? [], 'total'));
    }

    public function test_an_important_reschedule_proposes_a_draft_once_and_sends_nothing(): void
    {
        $event = new \Eventor\Catalog\Contract\Event\ActivityRescheduled(
            $this->organizer(),
            \Eventor\Shared\Domain\Id\EventId::fromString($this->event['eventId']),
            \Eventor\Shared\Domain\Id\ActivityId::fromString((string) $this->event['kids']),
            new \DateTimeImmutable('2026-11-22T09:00:00+01:00'),
            new \DateTimeImmutable('2026-11-22T10:00:00+01:00'),
            true,
            new \DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        );
        $this->publishEvent($event);
        $this->publishEvent($event);
        $this->drainStrict();

        self::assertSame(1, $this->dbInt("SELECT count(*) FROM communication.campaign WHERE state = 'draft' AND template_key = 'activity_changed'"));
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM communication.delivery'), 'a proposal sends nothing: sending is the decision of the organizer');
    }
}
