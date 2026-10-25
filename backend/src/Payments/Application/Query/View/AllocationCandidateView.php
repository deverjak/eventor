<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query\View;

/**
 * An order that could own an unmatched transaction, with the reasons why (same VS, same amount, Pairrot's suggestion).
 */
final readonly class AllocationCandidateView
{
    /**
     * @param list<string> $reasons
     */
    public function __construct(
        public string $paymentRequestId,
        public string $orderId,
        public ?string $variableSymbol,
        public MoneyView $amount,
        public MoneyView $outstanding,
        public string $requestStatus,
        public string $financialState,
        public array $reasons,
    ) {}
}
