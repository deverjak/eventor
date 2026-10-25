<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Shared\Application\Attention\AttentionType;

/**
 * Money arrived for an expired or cancelled participation (S05): restore it (Registration) or refund (Payments).
 * Both remedies belong to other modules that expose no command Contract yet, so they are links.
 */
#[AsAttentionHandler(AttentionType::LatePayment)]
final class LatePaymentHandler extends AbstractAttentionHandler
{
    protected function remedies(AttentionItemView $item): array
    {
        $actions = [AttentionAction::link('restore_participation', 'participation', $item->subjectId)];
        $order = self::payloadString($item, 'orderId');
        if (null !== $order) {
            $actions[] = AttentionAction::link('create_refund_task', 'order', $order);
        }

        return $actions;
    }
}
