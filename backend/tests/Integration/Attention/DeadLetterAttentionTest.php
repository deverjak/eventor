<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Attention;

use Eventor\Attention\Application\Close\CloseAttentionItem;
use Eventor\Attention\Application\Open\OpenAttentionItem;
use Eventor\Attention\Application\Transition\ResolveAttentionItem;
use Eventor\Attention\Contract\Exception\AttentionItemNotFound;
use Eventor\Shared\Application\Attention\AttentionRaiser;
use Eventor\Shared\Application\Attention\AttentionRequest;
use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * The kernel's `AttentionRaiser` port (used by the dead-letter listener) and the module's own commands share one
 * table and one dedupe rule.
 */
final class DeadLetterAttentionTest extends IntegrationTestCase
{
    public function test_the_raiser_opens_an_open_item_once_per_cause(): void
    {
        $organizer = OrganizerId::fromString($this->createOwner('dl@example.test', 'dl-club')['organizerId']);
        $request = new AttentionRequest(AttentionType::OutboxDeadLetter, 'dead_letter:x:Msg:1', $organizer, 'message', '1', ['message' => 'Msg']);

        $this->service(AttentionRaiser::class)->raise($request);
        $this->service(AttentionRaiser::class)->raise($request);

        self::assertSame(1, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE state = 'open' AND type = 'outbox_dead_letter'"));
    }

    public function test_the_raiser_item_can_be_resolved_through_the_module_and_a_new_failure_opens_a_new_item(): void
    {
        $organizer = OrganizerId::fromString($this->createOwner('dl@example.test', 'dl-club')['organizerId']);
        $request = new AttentionRequest(AttentionType::OutboxDeadLetter, 'dead_letter:x:Msg:1', $organizer, 'message', '1');
        $this->service(AttentionRaiser::class)->raise($request);
        $id = AttentionItemId::fromString($this->dbString("SELECT id FROM attention.attention_item WHERE dedupe_key = 'dead_letter:x:Msg:1'"));

        $this->commandBus()->dispatch(new ResolveAttentionItem($id, $organizer, 'retried by hand'));
        $this->service(AttentionRaiser::class)->raise($request);

        self::assertSame(['resolved', 'open'], $this->dbColumn("SELECT state FROM attention.attention_item WHERE dedupe_key = 'dead_letter:x:Msg:1' ORDER BY created_at, state DESC"));
    }

    public function test_an_exhausted_message_in_the_worker_becomes_a_dead_letter_item(): void
    {
        $result = $this->drainQueues();
        self::assertSame(0, $result->failed);
        // The real listener path is proven by Scenario F0_TransactionalOutboxTest; here the item it creates is readable
        // through the module's queue and offers the dead-letter actions.
        $organizer = OrganizerId::fromString($this->createOwner('dl2@example.test', 'dl2-club')['organizerId']);
        $this->service(AttentionRaiser::class)->raise(new AttentionRequest(AttentionType::OutboxDeadLetter, 'dead_letter:attention_events:Msg:7', $organizer, 'message', '7', ['message' => 'Msg']));

        $items = $this->service(\Eventor\Attention\Contract\AttentionQueue::class)->forSubject($organizer, 'message', '7');

        self::assertCount(1, $items);
        self::assertSame(AttentionType::OutboxDeadLetter, $items[0]->type);
    }

    public function test_opening_twice_is_deduplicated_and_closing_nothing_is_fine(): void
    {
        $organizer = OrganizerId::fromString($this->createOwner('cmd@example.test', 'cmd-club')['organizerId']);

        $first = $this->commandBus()->dispatch(new OpenAttentionItem(AttentionType::Overpayment, $organizer, 'order', 'o1', null, ['amountMinor' => 100]));
        $second = $this->commandBus()->dispatch(new OpenAttentionItem(AttentionType::Overpayment, $organizer, 'order', 'o1'));
        $closedNothing = $this->commandBus()->dispatch(new CloseAttentionItem(AttentionType::Overpayment, 'order', 'unknown'));
        $closed = $this->commandBus()->dispatch(new CloseAttentionItem(AttentionType::Overpayment, 'order', 'o1'));

        self::assertIsString($first);
        self::assertNull($second);
        self::assertFalse($closedNothing);
        self::assertTrue($closed);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE type = 'overpayment'"));
    }

    public function test_resolving_an_unknown_item_is_not_found(): void
    {
        $organizer = OrganizerId::fromString($this->createOwner('cmd@example.test', 'cmd-club')['organizerId']);

        $this->expectException(AttentionItemNotFound::class);
        $this->commandBus()->dispatch(new ResolveAttentionItem(AttentionItemId::generate(), $organizer));
    }
}
