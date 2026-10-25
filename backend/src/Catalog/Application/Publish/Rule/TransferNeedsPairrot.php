<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publish\Rule;

use Eventor\Catalog\Application\Publish\PublishCandidate;
use Eventor\Catalog\Application\Publish\PublishRule;
use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * A bank transfer needs a connected Pairrot account (ORG-02). Without the Identity facade the check fails closed.
 */
final readonly class TransferNeedsPairrot implements PublishRule
{
    public function __construct(private ?OrganizerSettings $settings = null) {}

    public function check(PublishCandidate $candidate): array
    {
        if (!$candidate->event->paymentRules()->allows(PaymentMethod::BankTransfer)) {
            return [];
        }
        if (null !== $this->settings && $this->settings->paymentSettings($candidate->event->organizerId())->canIssueTransferPayments()) {
            return [];
        }

        return [['path' => 'paymentRules.methods', 'message' => 'Bank transfer needs a connected bank account (Pairrot). Finish the payment onboarding or use cash.', 'code' => 'transfer_requires_pairrot']];
    }
}
