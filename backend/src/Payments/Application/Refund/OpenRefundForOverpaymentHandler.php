<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Refund;

use Eventor\Identity\Contract\OverpaymentHandling;
use Eventor\Payments\Application\Finance\LedgerBook;
use Eventor\Payments\Application\Port\OrganizerPaymentPolicy;
use Eventor\Payments\Contract\RefundReason;
use Eventor\Payments\Domain\Refund\RefundTask;
use Eventor\Payments\Domain\Refund\RefundTaskRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Policy "donation" / "ask" or no excess any more: nothing to open. Opening records RefundTaskOpened.')]
final readonly class OpenRefundForOverpaymentHandler
{
    public function __construct(
        private LedgerBook $book,
        private RefundTaskRepository $tasks,
        private OrganizerPaymentPolicy $policy,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    public function __invoke(OpenRefundForOverpayment $command): void
    {
        $organizer = $this->tenant->require();
        $order = OrderId::fromString($command->orderId);
        $finance = $this->book->lock($organizer, $order);
        $excess = $finance->overpaid();
        if (!$excess->isPositive()) {
            return;
        }

        $open = $this->tasks->findOpenForOrder($order);
        if (null !== $open) {
            $open->adjustAmount($excess);

            return;
        }
        if (OverpaymentHandling::Refund !== $this->policy->overpaymentHandling($organizer)) {
            return;
        }

        $reason = $finance->amountDue()->isZero()
            ? ('event_cancelled' === $finance->dueChangeReason() ? RefundReason::EventCancelled : RefundReason::Cancellation)
            : RefundReason::Overpayment;

        $task = RefundTask::open(
            $organizer,
            $order,
            $excess,
            $reason,
            null,
            AuditActor::system('payments.overpayment'),
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        $this->tasks->add($task);
        $this->book->refresh($finance, true);
    }
}
