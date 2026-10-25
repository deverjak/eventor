<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Shared\Application\Attention\AttentionType;

/**
 * A message exhausted its retries and sits in the `failed` transport. Retrying it is an operations task
 * (`messenger:failed:retry`); the link names the message so the UI or an operator can find it.
 */
#[AsAttentionHandler(AttentionType::OutboxDeadLetter)]
final class DeadLetterHandler extends AbstractAttentionHandler
{
    protected function remedies(AttentionItemView $item): array
    {
        return '' === $item->subjectId ? [] : [AttentionAction::link('retry_message', 'failed_message', $item->subjectId)];
    }
}
