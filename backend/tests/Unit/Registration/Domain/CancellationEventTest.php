<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Registration\Domain;

use Eventor\Registration\Application\EventPublisher\PublishRegistrationEvents;
use Eventor\Registration\Contract\CancellationReason;
use Eventor\Registration\Contract\CancelledBy;
use Eventor\Registration\Contract\Event\ParticipationCancelled;
use Eventor\Registration\Domain\Event\ParticipationWasCancelled;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The integration event of a cancellation carries a closed reason code, never the free text.
 */
final class CancellationEventTest extends TestCase
{
    public function test_the_published_event_has_the_code_and_no_free_text(): void
    {
        $sent = [];
        $bus = new class($sent) implements MessageBusInterface {
            /** @param list<object> $sent */
            public function __construct(public array &$sent) {}

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->sent[] = $message;

                return new Envelope($message);
            }
        };

        (new PublishRegistrationEvents($bus, new MockClock('2026-11-01 12:00:00')))->onCancelled(
            new ParticipationWasCancelled(OrganizerId::generate(), OrderId::generate(), ParticipationId::generate(), CancelledBy::Participant, CancellationReason::ParticipantRequest),
        );

        self::assertCount(1, $sent);
        self::assertInstanceOf(ParticipationCancelled::class, $sent[0]);
        self::assertSame(CancellationReason::ParticipantRequest, $sent[0]->reason);
    }
}
