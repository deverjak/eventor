<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\RecordManualPayment;

use Eventor\Payments\Application\Finance\LedgerBook;
use Eventor\Payments\Domain\Ledger\LedgerEntry;
use Eventor\Payments\Domain\Ledger\LedgerEntryRepository;
use Eventor\Payments\Domain\Ledger\LedgerSource;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Idempotency\IdempotencyKeyReused;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A repeated request with the same key books nothing; the first call recorded LedgerEntryAppended.')]
final readonly class RecordManualPaymentHandler
{
    public function __construct(
        private LedgerBook $book,
        private LedgerEntryRepository $ledger,
        private TenantContext $tenant,
        private AuditContext $audit,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{ledgerEntryId: string, alreadyRecorded: bool, state: string, received: int, amountDue: int}
     */
    public function __invoke(RecordManualPayment $command): array
    {
        $organizer = $this->tenant->require();
        $order = OrderId::fromString($command->orderId);
        $finance = $this->book->lock($organizer, $order);
        $amount = Money::of($command->amountMinor, Currency::CZK);

        $earlier = $this->ledger->findBySourceRef(LedgerSource::Manual, $command->idempotencyKey);
        if (null !== $earlier) {
            if (!$earlier->orderId()->equals($order) || !$earlier->amount()->equals($amount)) {
                throw IdempotencyKeyReused::create();
            }
            $entry = $earlier;
            $again = true;
        } else {
            $entry = LedgerEntry::manualCorrection(
                $organizer,
                $order,
                $amount,
                $command->idempotencyKey,
                $this->audit->actor() ?? AuditActor::system('payments'),
                $command->reason,
                \DateTimeImmutable::createFromInterface($this->clock->now()),
            );
            $this->book->append($finance, [$entry]);
            $again = false;
        }

        $view = $finance->view();

        return [
            'ledgerEntryId' => $entry->id()->toString(),
            'alreadyRecorded' => $again,
            'state' => $view->state->value,
            'received' => $view->received->minor,
            'amountDue' => $view->amountDue->minor,
        ];
    }
}
