<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Shared\Application\Attention\AttentionType;

/**
 * An allocation was revoked in Pairrot: review the order and contact the participant.
 */
#[AsAttentionHandler(AttentionType::PaymentRevoked)]
final class PaymentRevokedHandler extends AbstractAttentionHandler
{
    protected function remedies(AttentionItemView $item): array
    {
        $order = self::payloadString($item, 'orderId');

        return null === $order ? [] : [AttentionAction::link('review_order', 'order', $order)];
    }
}
