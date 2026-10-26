<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario\Communication;

use Eventor\Catalog\Contract\Event\EventCancelled;
use Eventor\Communication\Application\Campaign\CreateCampaign;
use Eventor\Communication\Application\Campaign\StartCampaign;
use Eventor\Communication\Application\Suppression\AddSuppression;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\SuppressionScope;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Legal\Contract\Event\ConsentWithdrawn;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Payments\Contract\Event\RefundTaskSettled;
use Eventor\Registration\Contract\Event\SelfServiceLinkRequested;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\ConfirmationId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\RefundTaskId;
use Eventor\Shared\Domain\Id\SelfServiceTokenId;
use Eventor\Shared\Domain\Money;
use Eventor\Tests\Support\Catalog\Json;

/**
 * COM-01 and PRV-07: one message per fact, whatever the transport delivers twice. Registration, Catalog, Payments,
 * Legal's consent ledger and Attention are all real.
 */
final class TransactionalMessagesTest extends CommunicationScenarioCase
{
    /** @var array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string} */
    private array $event;
    private \DateTimeImmutable $at;

    protected function setUp(): void
    {
        parent::setUp();
        $this->at = new \DateTimeImmutable('2026-11-01T12:00:00+00:00');
        $this->event = $this->openEvent(kids: true);
        $this->publishAndOpen($this->event['eventId']);
    }

    /**
     * A parent for the trail and a child for the children's race: one order, two activities.
     *
     * @return array{orderId: string}
     */
    private function familyOrder(string $email): array
    {
        $placed = $this->placeOrder([
            'items' => [
                ['personRef' => 'otec', 'activityId' => $this->event['trail'], 'variantId' => $this->event['trailVariant']],
                ['personRef' => 'dite', 'activityId' => (string) $this->event['kids'], 'variantId' => (string) $this->event['kidsVariant']],
            ],
            'persons' => [
                ['personRef' => 'otec', 'answers' => ['first_name' => 'Karel', 'last_name' => 'Novák', 'birth_date' => '1985-03-04']],
                ['personRef' => 'dite', 'answers' => ['first_name' => 'Anna', 'last_name' => 'Nováková', 'birth_date' => '2018-02-03', 'guardian_first_name' => 'Karel', 'guardian_last_name' => 'Novák', 'guardian_email' => $email]],
            ],
            'orderAnswers' => ['orderer_first_name' => 'Karel', 'orderer_last_name' => 'Novák', 'orderer_email' => $email],
            'paymentMethod' => 'cash',
        ], slug: $this->event['slug']);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        \assert(\is_array($placed) && \is_string($placed['orderId']));

        return ['orderId' => $placed['orderId']];
    }

    /**
     * @return list<string>
     */
    private function subjects(string $email): array
    {
        return array_map(static fn($mail): string => (string) $mail->getSubject(), $this->mailer()->emailsTo($email));
    }

    public function test_a_cash_order_is_confirmed_once_with_the_participants(): void
    {
        $order = $this->familyOrder('eva@example.test');
        $this->drainStrict();

        $number = $this->dbString('SELECT number FROM registration.orders WHERE id = ?', [$order['orderId']]);
        self::assertSame(['Registrace '.$number.' přijata – Borský podzimní víkend 2026'], $this->subjects('eva@example.test'));
        $text = (string) $this->mailer()->emailsTo('eva@example.test')[0]->getTextBody();
        self::assertStringContainsString('Karel Novák: Trail 5 km', $text);
        self::assertStringContainsString('Anna Nováková: Dětský závod', $text);
    }

    public function test_an_expired_reservation_is_one_message_per_order(): void
    {
        $this->connectPairrot();
        $transfer = $this->openEvent('prevod', methods: ['bank_transfer']);
        $this->publishAndOpen($transfer['eventId']);
        $order = $this->orderFor($transfer, 'exp@example.test', 'bank_transfer');
        $this->drainWithMailRetries();

        self::mockTime('+49 hours');
        $this->sweepReservations();
        $this->drainWithMailRetries();

        self::assertContains('Rezervace vypršela – Borský podzimní víkend 2026', $this->subjects('exp@example.test'));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM communication.delivery WHERE order_id = ? AND trigger_key = 'reservation_expired'", [$order['orderId']]));
    }

    public function test_self_service_link_and_refund_messages(): void
    {
        $order = $this->familyOrder('eva@example.test');
        $this->drainStrict();
        $this->mailer()->reset();
        $orderId = OrderId::fromString($order['orderId']);

        $this->publishEvent(new SelfServiceLinkRequested($this->organizer(), $orderId, SelfServiceTokenId::generate(), $this->at));
        $this->publishEvent(new RefundTaskSettled($this->organizer(), RefundTaskId::generate(), $orderId, Money::of(15_000), $this->at));
        $this->drainStrict();

        $number = $this->dbString('SELECT number FROM registration.orders WHERE id = ?', [$order['orderId']]);
        $byTrigger = [];
        foreach ($this->mailer()->emailsTo('eva@example.test') as $mail) {
            $byTrigger[(string) $mail->getSubject()] = (string) $mail->getTextBody();
        }
        self::assertCount(2, $byTrigger);
        self::assertStringContainsString('/s/ss1_', $byTrigger['Odkaz na správu registrace '.$number]);
        self::assertMatchesRegularExpression('/150\s*Kč/u', $byTrigger['Vrácení platby – objednávka '.$number]);
    }

    public function test_event_cancelled_writes_to_everybody_once(): void
    {
        $this->familyOrder('eva@example.test');
        $this->orderFor($this->event, 'petr@example.test');
        $this->drainStrict();
        $this->mailer()->reset();

        $event = new EventCancelled($this->organizer(), EventId::fromString($this->event['eventId']), 'weather', $this->at);
        $this->publishEvent($event);
        $this->publishEvent($event);
        $this->drainStrict();

        self::assertCount(1, array_filter($this->subjects('eva@example.test'), static fn(string $s): bool => str_starts_with($s, 'Akce zrušena')), 'one message although the order has two activities');
        self::assertCount(1, array_filter($this->subjects('petr@example.test'), static fn(string $s): bool => str_starts_with($s, 'Akce zrušena')));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM communication.campaign WHERE template_key = 'event_cancelled'"));
        self::assertSame('sent', $this->dbString("SELECT state FROM communication.campaign WHERE template_key = 'event_cancelled'"));
    }

    public function test_a_marketing_campaign_needs_a_granted_consent(): void
    {
        $this->orderFor($this->event, 'eva@example.test');
        $this->drainStrict();
        $this->mailer()->reset();

        $created = $this->commandBus()->dispatch(new CreateCampaign($this->event['eventId'], 'Newsletter', CampaignPurpose::Marketing, MessageLocale::Czech, TemplateKey::Custom, 'Novinky', 'Ahoj {{ recipient.name }}', new CampaignAudienceSpec()), TenantStamp::of($this->organizer()));
        \assert(\is_array($created));

        // PRV-07: nobody granted the marketing consent, so the audience is empty (the restriction is not a UI switch).
        $campaignId = Json::str($created, 'campaignId');
        self::assertSame(['recipients' => 0], $this->commandBus()->dispatch(new StartCampaign($campaignId), TenantStamp::of($this->organizer())));
        self::assertSame('sent', $this->dbString('SELECT state FROM communication.campaign WHERE id = ?', [$campaignId]));
        self::assertCount(0, $this->mailer()->emails());
    }

    public function test_consent_withdrawn_is_confirmed_and_a_hard_suppression_stops_even_transactional_mail(): void
    {
        $order = OrderId::fromString($this->familyOrder('eva@example.test')['orderId']);
        $this->drainStrict();
        $this->mailer()->reset();

        $this->publishEvent(new ConsentWithdrawn($this->organizer(), ConfirmationId::generate(), LegalRequirementId::generate(), RequirementType::Consent, null, $order, $this->at));
        $this->drainStrict();
        self::assertSame(['Potvrzení odvolání souhlasu'], $this->subjects('eva@example.test'));

        $this->commandBus()->dispatch(new AddSuppression('EVA@example.test', SuppressionScope::All), TenantStamp::of($this->organizer()));
        $this->publishEvent(new SelfServiceLinkRequested($this->organizer(), $order, SelfServiceTokenId::generate(), $this->at));
        $this->drainStrict();

        self::assertCount(1, $this->mailer()->emailsTo('eva@example.test'));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM communication.delivery WHERE trigger_key = 'self_service_link' AND state = 'skipped' AND last_error = 'suppressed'"));
    }
}
