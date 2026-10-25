<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Refund;

use Eventor\Payments\Application\Finance\LedgerBook;
use Eventor\Payments\Contract\RefundReason;
use Eventor\Payments\Domain\Refund\RefundTask;
use Eventor\Payments\Domain\Refund\RefundTaskException;
use Eventor\Payments\Domain\Refund\RefundTaskRepository;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class OpenRefundTaskHandler
{
    public function __construct(
        private LedgerBook $book,
        private RefundTaskRepository $tasks,
        private TenantContext $tenant,
        private AuditContext $audit,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{refundTaskId: string}
     */
    public function __invoke(OpenRefundTask $command): array
    {
        $organizer = $this->tenant->require();
        $order = OrderId::fromString($command->orderId);
        $finance = $this->book->lock($organizer, $order);
        if ($this->tasks->hasOpenForOrder($order)) {
            throw RefundTaskException::alreadyOpen();
        }

        $task = RefundTask::open(
            $organizer,
            $order,
            Money::of($command->amountMinor, $finance->currency()),
            RefundReason::from($command->reason),
            $command->note,
            $this->audit->actor() ?? AuditActor::system('payments'),
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        $this->tasks->add($task);
        $this->book->refresh($finance, true);

        return ['refundTaskId' => $task->id()->toString()];
    }
}
