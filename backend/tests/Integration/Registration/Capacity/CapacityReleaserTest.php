<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration\Capacity;

use Eventor\Registration\Application\Capacity\CapacityReleaser;
use Eventor\Registration\Contract\CapacityReleaseCause;
use Eventor\Registration\Contract\Event\CapacityReleased;
use Eventor\Shared\Domain\Id\ParticipationId;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class CapacityReleaserTest extends CapacityTestCase
{
    /**
     * @return list<CapacityReleased>
     */
    private function published(): array
    {
        $transport = static::getContainer()->get('messenger.transport.registration_events');
        \assert($transport instanceof InMemoryTransport);

        $events = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof CapacityReleased) {
                $events[] = $message;
            }
        }

        return $events;
    }

    public function test_it_frees_the_seats_and_publishes_capacity_released_with_the_cause(): void
    {
        $bucket = $this->bucket(4);
        $participation = $this->claim([$bucket], 2);

        $released = $this->service(CapacityReleaser::class)->release($this->organizer, [$participation], CapacityReleaseCause::Expired);

        self::assertSame(2, $released->seats);
        self::assertSame(0, $this->counters($bucket)['held']);
        $events = $this->published();
        self::assertCount(1, $events);
        self::assertSame(CapacityReleaseCause::Expired, $events[0]->cause);
        self::assertSame(2, $events[0]->seats);
        self::assertTrue($events[0]->organizerId->equals($this->organizer));
        self::assertTrue($events[0]->bucketIds[0]->equals($bucket));
    }

    public function test_it_publishes_nothing_when_nothing_was_released(): void
    {
        $this->service(CapacityReleaser::class)->release($this->organizer, [ParticipationId::generate()], CapacityReleaseCause::Cancelled);

        self::assertSame([], $this->published());
    }
}
