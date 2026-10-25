<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Attention;

use Eventor\Attention\Contract\AttentionState;
use Eventor\Attention\Contract\Exception\AttentionActionNotAvailable;
use Eventor\Attention\Domain\AttentionItem;
use Eventor\Attention\Domain\Event\AttentionItemAcknowledged;
use Eventor\Attention\Domain\Event\AttentionItemClosed;
use Eventor\Attention\Domain\Event\AttentionItemOpened;
use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\OrganizerId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;

/**
 * The lifecycle open -> acknowledged -> resolved | dismissed, with the same definition as services.php.
 */
final class AttentionItemWorkflowTest extends TestCase
{
    private const string NOW = '2026-11-01T12:00:00+00:00';

    public function test_a_new_item_is_open_and_records_that_it_was_opened(): void
    {
        $item = $this->open();

        self::assertSame(AttentionState::Open, $item->getState());
        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AttentionItemOpened::class, $events[0]);
        self::assertSame('attention.opened', $events[0]->auditChange()->action);
    }

    public function test_open_to_acknowledged_to_resolved(): void
    {
        $item = $this->open();
        $item->releaseEvents();

        $item->acknowledge($this->machine(), 'user-1', $this->now());
        self::assertSame(AttentionState::Acknowledged, $item->getState());

        $item->resolve($this->machine(), 'user-1', AttentionItem::RESOLUTION_ACTION, 'called the participant', $this->now());
        self::assertSame(AttentionState::Resolved, $item->getState());

        $events = $item->releaseEvents();
        self::assertInstanceOf(AttentionItemAcknowledged::class, $events[0]);
        self::assertInstanceOf(AttentionItemClosed::class, $events[1]);
        self::assertSame('attention.resolved', $events[1]->auditChange()->action);
        self::assertSame('called the participant', $events[1]->auditChange()->reason);
    }

    public function test_an_open_item_can_be_dismissed_directly(): void
    {
        $item = $this->open();
        $item->releaseEvents();

        $item->dismiss($this->machine(), 'user-1', 'not our problem', $this->now());

        self::assertSame(AttentionState::Dismissed, $item->getState());
        $event = $item->releaseEvents()[0];
        self::assertInstanceOf(AttentionItemClosed::class, $event);
        self::assertSame('attention.dismissed', $event->auditChange()->action);
    }

    public function test_a_closed_item_accepts_no_further_transition(): void
    {
        $item = $this->open();
        $item->resolve($this->machine(), 'user-1', AttentionItem::RESOLUTION_AUTO, null, $this->now());

        foreach ([
            fn() => $item->acknowledge($this->machine(), 'u', $this->now()),
            fn() => $item->resolve($this->machine(), 'u', 'action', null, $this->now()),
            fn() => $item->dismiss($this->machine(), 'u', null, $this->now()),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('A resolved item must not change.');
            } catch (AttentionActionNotAvailable $e) {
                self::assertStringContainsString('resolved', $e->getMessage());
            }
        }
    }

    public function test_an_acknowledged_item_cannot_be_acknowledged_again(): void
    {
        $item = $this->open();
        $item->acknowledge($this->machine(), 'u', $this->now());

        $this->expectException(AttentionActionNotAvailable::class);
        $item->acknowledge($this->machine(), 'u', $this->now());
    }

    public function test_the_state_knows_whether_it_is_unresolved(): void
    {
        self::assertTrue(AttentionState::Open->isUnresolved());
        self::assertTrue(AttentionState::Acknowledged->isUnresolved());
        self::assertFalse(AttentionState::Resolved->isUnresolved());
        self::assertFalse(AttentionState::Dismissed->isUnresolved());
    }

    private function open(): AttentionItem
    {
        return AttentionItem::open(
            AttentionItemId::generate(),
            OrganizerId::generate(),
            AttentionType::LatePayment,
            'late_payment:participation:p1',
            'participation',
            'p1',
            null,
            ['orderId' => 'o1'],
            $this->now(),
        );
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    private function machine(): StateMachine
    {
        $definition = new Definition(
            ['open', 'acknowledged', 'resolved', 'dismissed'],
            [
                new Transition('acknowledge', 'open', 'acknowledged'),
                new Transition('resolve', 'open', 'resolved'),
                new Transition('resolve', 'acknowledged', 'resolved'),
                new Transition('dismiss', 'open', 'dismissed'),
                new Transition('dismiss', 'acknowledged', 'dismissed'),
            ],
            'open',
        );

        return new StateMachine($definition, new MethodMarkingStore(true, 'state'));
    }
}
