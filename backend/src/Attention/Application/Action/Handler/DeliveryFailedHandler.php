<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Shared\Application\Attention\AttentionType;

/**
 * A message failed for good (summarised per campaign): retry it in Communication (no Contract hook yet).
 */
#[AsAttentionHandler(AttentionType::DeliveryFailed)]
final class DeliveryFailedHandler extends AbstractAttentionHandler
{
    protected function remedies(AttentionItemView $item): array
    {
        return [AttentionAction::link('retry_failed_deliveries', $item->subjectType, $item->subjectId)];
    }
}
