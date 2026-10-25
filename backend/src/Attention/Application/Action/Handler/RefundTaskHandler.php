<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Shared\Application\Attention\AttentionType;

/**
 * A manual refund task (PAY-10). Settling it in Payments closes the item (`payments.refund_task_settled`).
 */
#[AsAttentionHandler(AttentionType::RefundTask)]
final class RefundTaskHandler extends AbstractAttentionHandler
{
    protected function remedies(AttentionItemView $item): array
    {
        return [AttentionAction::link('settle_refund', 'refund_task', $item->subjectId)];
    }
}
