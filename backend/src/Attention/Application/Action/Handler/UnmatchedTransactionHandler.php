<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Shared\Application\Attention\AttentionType;

/**
 * A bank transaction without an order (PAY-06). Allocating it in Payments closes the item by itself
 * (`payments.transaction_allocated`).
 */
#[AsAttentionHandler(AttentionType::UnmatchedPayment)]
final class UnmatchedTransactionHandler extends AbstractAttentionHandler
{
    protected function remedies(AttentionItemView $item): array
    {
        return [AttentionAction::link('allocate_transaction', 'bank_transaction', $item->subjectId)];
    }
}
