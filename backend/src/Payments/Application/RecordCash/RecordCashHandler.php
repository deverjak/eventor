<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\RecordCash;

use Eventor\Payments\Application\Finance\CashRegister;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Eventor\Shared\Domain\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A repeated request with the same cash key books nothing; the first call recorded LedgerEntryAppended.')]
final readonly class RecordCashHandler
{
    public function __construct(
        private CashRegister $register,
        private TenantContext $tenant,
    ) {}

    /**
     * @return array{ledgerEntryId: string, alreadyRecorded: bool, state: string, received: int, amountDue: int}
     */
    public function __invoke(RecordCash $command): array
    {
        $receipt = $this->register->record(
            $this->tenant->require(),
            OrderId::fromString($command->orderId),
            Money::of($command->amountMinor, Currency::CZK),
            null,
            $command->idempotencyKey,
            $command->note,
        );

        return [
            'ledgerEntryId' => $receipt->ledgerEntryId->toString(),
            'alreadyRecorded' => $receipt->alreadyRecorded,
            'state' => $receipt->finance->state->value,
            'received' => $receipt->finance->received->minor,
            'amountDue' => $receipt->finance->amountDue->minor,
        ];
    }
}
