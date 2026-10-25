<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Shared\Application\Attention\AttentionType;

/**
 * More money than due (S07): refund it (link to Payments), or keep it as a donation by resolving with a note.
 */
#[AsAttentionHandler(AttentionType::Overpayment)]
final class OverpaymentHandler extends AbstractAttentionHandler
{
    protected function remedies(AttentionItemView $item): array
    {
        return [AttentionAction::link('create_refund_task', 'order', $item->subjectId)];
    }
}
