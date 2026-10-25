<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Refund;

use Eventor\Payments\Application\Finance\LedgerBook;
use Eventor\Payments\Domain\Ledger\LedgerEntry;
use Eventor\Payments\Domain\Refund\RefundTaskException;
use Eventor\Payments\Domain\Refund\RefundTaskNotFound;
use Eventor\Payments\Domain\Refund\RefundTaskRepository;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Id\RefundTaskId;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SettleRefundTaskHandler
{
    public function __construct(
        private LedgerBook $book,
        private RefundTaskRepository $tasks,
        #[Autowire(service: 'state_machine.refund_task')]
        private WorkflowInterface $workflow,
        private TenantContext $tenant,
        private AuditContext $audit,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{refundTaskId: string, ledgerEntryId: string, state: string}
     */
    public function __invoke(SettleRefundTask $command): array
    {
        $organizer = $this->tenant->require();
        $task = $this->tasks->lock(RefundTaskId::fromString($command->refundTaskId)) ?? throw RefundTaskNotFound::forId($command->refundTaskId);
        if (!$this->workflow->can($task, 'settle')) {
            throw RefundTaskException::notOpen($task->id());
        }

        $finance = $this->book->lock($organizer, $task->orderId());
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $actor = $this->audit->actor() ?? AuditActor::system('payments');

        $entry = LedgerEntry::refundSettled($organizer, $task->orderId(), $task->amount(), $task->id()->toString(), $actor, $command->note, $now);
        $this->workflow->apply($task, 'settle');
        $task->settle($command->method, $command->reference, $command->note, $actor, $entry->id(), $now);
        $fold = $this->book->append($finance, [$entry], false);

        return [
            'refundTaskId' => $task->id()->toString(),
            'ledgerEntryId' => $entry->id()->toString(),
            'state' => $fold->state->value,
        ];
    }
}
