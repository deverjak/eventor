<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\ChangeOrderAmountDue;

use Eventor\Payments\Application\Finance\LedgerBook;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Eventor\Shared\Domain\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Redelivery of the same amount changes nothing; a real change records AmountDueChanged and OrderFinanceRecomputed.')]
final readonly class ChangeOrderAmountDueHandler
{
    public function __construct(
        private LedgerBook $book,
        private TenantContext $tenant,
    ) {}

    /**
     * @return array{changed: bool, state: string, received: int, amountDue: int}
     */
    public function __invoke(ChangeOrderAmountDue $command): array
    {
        $finance = $this->book->lock($this->tenant->require(), OrderId::fromString($command->orderId));
        $changed = null !== $this->book->changeAmountDue($finance, Money::of($command->newAmountDueMinor, $finance->currency()), $command->reason);
        $view = $finance->view();

        return ['changed' => $changed, 'state' => $view->state->value, 'received' => $view->received->minor, 'amountDue' => $view->amountDue->minor];
    }
}
