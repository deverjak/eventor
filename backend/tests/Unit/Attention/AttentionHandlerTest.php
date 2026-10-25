<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Attention;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Application\Action\Handler\GenericAttentionHandler;
use Eventor\Attention\Application\Transition\AcknowledgeAttentionItem;
use Eventor\Attention\Application\Transition\DismissAttentionItem;
use Eventor\Attention\Application\Transition\ResolveAttentionItem;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Attention\Contract\AttentionState;
use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\OrganizerId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class AttentionHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<AbstractAttentionHandler>, AttentionType}>
     */
    public static function handlers(): iterable
    {
        $dir = \dirname(__DIR__, 3).'/src/Attention/Application/Action/Handler';
        foreach ((new Finder())->files()->in($dir)->name('*Handler.php') as $file) {
            /** @var class-string<AbstractAttentionHandler> $class */
            $class = 'Eventor\\Attention\\Application\\Action\\Handler\\'.$file->getBasename('.php');
            $attributes = (new \ReflectionClass($class))->getAttributes(AsAttentionHandler::class);
            if ([] !== $attributes) {
                yield $file->getBasename('.php') => [$class, $attributes[0]->newInstance()->type];
            }
        }
    }

    public function test_the_book_s_types_are_covered(): void
    {
        $covered = array_map(static fn(array $case): string => $case[1]->value, iterator_to_array(self::handlers(), false));

        foreach (['late_payment', 'overpayment', 'unmatched_payment', 'delivery_failed', 'consent_withdrawn', 'outbox_dead_letter', 'payment_request_issue_failed', 'payment_revoked', 'integration_degraded', 'refund_task', 'missing_confirmation'] as $type) {
            self::assertContains($type, $covered, "no #[AsAttentionHandler] for $type");
        }
        self::assertCount(\count(array_unique($covered)), $covered, 'one handler per type');
    }

    /**
     * @param class-string<AbstractAttentionHandler> $class
     */
    #[DataProvider('handlers')]
    public function test_every_open_item_offers_acknowledge_resolve_dismiss_and_every_command_action_maps_to_a_command(string $class, AttentionType $type): void
    {
        $handler = new $class();
        $item = $this->item($type, AttentionState::Open);

        $keys = array_map(static fn(AttentionAction $a): string => $a->key, $handler->actions($item));

        self::assertContains('acknowledge', $keys);
        self::assertContains('resolve', $keys);
        self::assertContains('dismiss', $keys);
        foreach ($handler->actions($item) as $action) {
            if (AttentionAction::KIND_COMMAND === $action->kind) {
                self::assertNotNull($handler->commandFor($item, OrganizerId::generate(), $action->key, null), $action->key);
            } else {
                self::assertNotNull($action->target, 'a link names its target');
                self::assertNull($handler->commandFor($item, OrganizerId::generate(), $action->key, null), 'a link is not executed here');
            }
        }
    }

    /**
     * @param class-string<AbstractAttentionHandler> $class
     */
    #[DataProvider('handlers')]
    public function test_closed_items_offer_nothing(string $class, AttentionType $type): void
    {
        foreach ([AttentionState::Resolved, AttentionState::Dismissed] as $state) {
            self::assertSame([], (new $class())->actions($this->item($type, $state)));
        }
    }

    public function test_an_acknowledged_item_no_longer_offers_acknowledge(): void
    {
        $keys = array_map(static fn(AttentionAction $a): string => $a->key, (new GenericAttentionHandler())->actions($this->item(AttentionType::PairrotReview, AttentionState::Acknowledged)));

        self::assertSame(['resolve', 'dismiss'], $keys);
    }

    public function test_the_generic_actions_build_the_transition_commands(): void
    {
        $handler = new GenericAttentionHandler();
        $item = $this->item(AttentionType::CapacityDrift, AttentionState::Open);
        $organizer = OrganizerId::generate();

        self::assertInstanceOf(AcknowledgeAttentionItem::class, $handler->commandFor($item, $organizer, 'acknowledge', null));
        $resolve = $handler->commandFor($item, $organizer, 'resolve', 'done');
        self::assertInstanceOf(ResolveAttentionItem::class, $resolve);
        self::assertSame('done', $resolve->note);
        self::assertInstanceOf(DismissAttentionItem::class, $handler->commandFor($item, $organizer, 'dismiss', null));
        self::assertNull($handler->commandFor($item, $organizer, 'hack', null));
    }

    public function test_a_late_payment_links_to_the_participation_and_the_refund(): void
    {
        $handlers = iterator_to_array(self::handlers());
        $class = $handlers['LatePaymentHandler'][0];
        $item = $this->item(AttentionType::LatePayment, AttentionState::Open, ['orderId' => 'order-1']);

        $links = array_values(array_filter((new $class())->actions($item), static fn(AttentionAction $a): bool => AttentionAction::KIND_LINK === $a->kind));

        self::assertSame(['restore_participation', 'create_refund_task'], array_map(static fn(AttentionAction $a): string => $a->key, $links));
        self::assertSame(['resource' => 'participation', 'id' => 'subject-1'], $links[0]->target);
        self::assertSame(['resource' => 'order', 'id' => 'order-1'], $links[1]->target);
    }

    /**
     * @param array<string, scalar|null> $payload
     */
    private function item(AttentionType $type, AttentionState $state, array $payload = []): AttentionItemView
    {
        return new AttentionItemView(AttentionItemId::generate(), $type, $state, 'participation', 'subject-1', null, new \DateTimeImmutable('2026-11-01T12:00:00+00:00'), $payload);
    }
}
