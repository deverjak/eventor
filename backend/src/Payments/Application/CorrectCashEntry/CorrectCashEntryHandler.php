<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\CorrectCashEntry;

use Eventor\Payments\Application\Finance\LedgerBook;
use Eventor\Payments\Domain\Ledger\LedgerEntry;
use Eventor\Payments\Domain\Ledger\LedgerEntryKind;
use Eventor\Payments\Domain\Ledger\LedgerEntryNotFound;
use Eventor\Payments\Domain\Ledger\LedgerEntryRepository;
use Eventor\Payments\Domain\Ledger\LedgerSource;
use Eventor\Payments\Domain\Ledger\NotCorrectable;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Idempotency\IdempotencyKeyReused;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\LedgerEntryId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A repeated correction with the same key books nothing; the first call recorded LedgerEntryAppended.')]
final readonly class CorrectCashEntryHandler
{
    public function __construct(
        private LedgerBook $book,
        private LedgerEntryRepository $ledger,
        private TenantContext $tenant,
        private AuditContext $audit,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{correctionId: string, replacementId: string|null, alreadyRecorded: bool, state: string, received: int, amountDue: int}
     */
    public function __invoke(CorrectCashEntry $command): array
    {
        $organizer = $this->tenant->require();
        $order = OrderId::fromString($command->orderId);
        $finance = $this->book->lock($organizer, $order);

        $correctionKey = $command->idempotencyKey.':correction';
        $earlier = $this->ledger->findBySourceRef(LedgerSource::Cash, $correctionKey);
        if (null !== $earlier) {
            if ($earlier->compensatesId()?->toString() !== $command->ledgerEntryId) {
                throw IdempotencyKeyReused::create();
            }
            $replacement = $this->ledger->findBySourceRef(LedgerSource::Cash, $command->idempotencyKey.':replacement');

            return $this->result($earlier, $replacement, true, $finance->view());
        }

        $original = $this->ledger->find(LedgerEntryId::fromString($command->ledgerEntryId));
        if (null === $original || !$original->orderId()->equals($order) || !$original->organizerId()->equals($organizer)) {
            throw LedgerEntryNotFound::forId($command->ledgerEntryId);
        }
        if (LedgerEntryKind::Cash !== $original->kind() || null !== $this->ledger->findCorrectionOf($original->id())) {
            throw NotCorrectable::create($original->id());
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $actor = $this->audit->actor() ?? AuditActor::system('payments');
        $correction = LedgerEntry::cashCorrection($original, $correctionKey, $actor, $command->reason, $now);
        $entries = [$correction];
        $replacement = null;
        if (null !== $command->replacementAmountMinor) {
            $replacement = LedgerEntry::cash(
                $organizer,
                $order,
                Money::of($command->replacementAmountMinor, Currency::CZK),
                $original->stationId(),
                $command->idempotencyKey.':replacement',
                $actor,
                'Replaces '.$original->id()->toString().': '.$command->reason,
                $now,
            );
            $entries[] = $replacement;
        }

        $this->book->append($finance, $entries);

        return $this->result($correction, $replacement, false, $finance->view());
    }

    /**
     * @return array{correctionId: string, replacementId: string|null, alreadyRecorded: bool, state: string, received: int, amountDue: int}
     */
    private function result(LedgerEntry $correction, ?LedgerEntry $replacement, bool $again, \Eventor\Shared\Domain\Finance\FinancialStateView $view): array
    {
        return [
            'correctionId' => $correction->id()->toString(),
            'replacementId' => $replacement?->id()->toString(),
            'alreadyRecorded' => $again,
            'state' => $view->state->value,
            'received' => $view->received->minor,
            'amountDue' => $view->amountDue->minor,
        ];
    }
}
