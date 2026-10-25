<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Shared\Application\Attention\AttentionType;

/**
 * Creating the payment request in Pairrot failed for good (S08): retry it (Payments, no Contract hook yet).
 */
#[AsAttentionHandler(AttentionType::PaymentRequestIssueFailed)]
final class PaymentRequestIssueFailedHandler extends AbstractAttentionHandler
{
    protected function remedies(AttentionItemView $item): array
    {
        return [AttentionAction::link('retry_issue_payment_request', 'payment_request', $item->subjectId)];
    }
}
