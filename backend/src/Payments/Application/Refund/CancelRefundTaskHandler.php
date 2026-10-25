<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Refund;

use Eventor\Payments\Application\Finance\LedgerBook;
use Eventor\Payments\Domain\Refund\RefundTaskException;
use Eventor\Payments\Domain\Refund\RefundTaskNotFound;
use Eventor\Payments\Domain\Refund\RefundTaskRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\RefundTaskId;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CancelRefundTaskHandler
{
    public function __construct(
        private LedgerBook $book,
        private RefundTaskRepository $tasks,
        #[Autowire(service: 'state_machine.refund_task')]
        private WorkflowInterface $workflow,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{refundTaskId: string, state: string}
     */
    public function __invoke(CancelRefundTask $command): array
    {
        $task = $this->tasks->lock(RefundTaskId::fromString($command->refundTaskId)) ?? throw RefundTaskNotFound::forId($command->refundTaskId);
        if (!$this->workflow->can($task, 'cancel')) {
            throw RefundTaskException::notOpen($task->id());
        }

        $finance = $this->book->lock($this->tenant->require(), $task->orderId());
        $this->workflow->apply($task, 'cancel');
        $task->cancel($command->reason, \DateTimeImmutable::createFromInterface($this->clock->now()));
        $fold = $this->book->refresh($finance, false);

        return ['refundTaskId' => $task->id()->toString(), 'state' => $fold->state->value];
    }
}
