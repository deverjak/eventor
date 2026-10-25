<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Catalog\Contract\Event\CapacityChanged;
use Eventor\Identity\Contract\Event\MembershipChanged;
use Eventor\Legal\Contract\Event\ConfirmationRecorded;
use Eventor\Legal\Contract\Event\ReconfirmationRequested;
use Eventor\Payments\Contract\Event\CashRecorded;
use Eventor\Payments\Contract\Event\OrderPaymentChanged;
use Eventor\Payments\Contract\Event\PaymentRequestIssued;
use Eventor\Registration\Contract\Event\CapacityReleased;
use Eventor\Registration\Contract\Event\OrderAmountChanged;
use Eventor\Registration\Contract\Event\OrderPlaced;
use Eventor\Registration\Contract\Event\ParticipationsConfirmed;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Money;
use Eventor\Tests\Support\EventSampler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The few events whose constructor refuses a meaningless payload (an order without participations, a negative
 * limit...). Everything else is a plain fact and is covered by the shape and round-trip tests.
 */
final class ContractEventInvariantsTest extends TestCase
{
    /**
     * @param class-string         $class
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidPayloads')]
    public function test_a_meaningless_payload_is_refused(string $class, array $override): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EventSampler::sample($class, $override);
    }

    /**
     * @return iterable<string, array{class-string, array<string, mixed>}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'order without participations' => [OrderPlaced::class, ['participationIds' => []]];
        yield 'negative amount due' => [OrderPlaced::class, ['amountDue' => Money::of(-1)]];
        yield 'confirmation of nobody' => [ParticipationsConfirmed::class, ['participationIds' => []]];
        yield 'release of no bucket' => [CapacityReleased::class, ['bucketIds' => []]];
        yield 'release of no seat' => [CapacityReleased::class, ['seats' => 0]];
        yield 'negative limit' => [CapacityChanged::class, ['newLimit' => -1]];
        yield 'order changes currency' => [OrderAmountChanged::class, ['previousAmountDue' => Money::of(100, Currency::CZK), 'newAmountDue' => Money::of(100, Currency::EUR)]];
        yield 'payment request for nothing' => [PaymentRequestIssued::class, ['amount' => Money::zero()]];
        yield 'cash is positive' => [CashRecorded::class, ['amount' => Money::of(-5)]];
        yield 'ledger version starts at 1' => [OrderPaymentChanged::class, ['ledgerVersion' => 0]];
        yield 'reconfirmation of nobody' => [ReconfirmationRequested::class, ['participationIds' => [], 'orderIds' => []]];
        yield 'confirmation of nothing' => [ConfirmationRecorded::class, ['participationId' => null, 'orderId' => null]];
        yield 'membership change without roles' => [MembershipChanged::class, ['previousRole' => null, 'newRole' => null]];
    }

    public function test_a_sensible_payload_is_accepted(): void
    {
        $event = EventSampler::sample(OrderPlaced::class, ['amountDue' => Money::zero()]);

        self::assertInstanceOf(OrderPlaced::class, $event);
        self::assertCount(1, $event->participationIds);
    }
}
