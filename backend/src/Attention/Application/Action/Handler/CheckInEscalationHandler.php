<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Shared\Application\Attention\AttentionType;

/**
 * An escalation from the check-in desk (CHK-09), stored as `missing_confirmation` (the book's type for it; see
 * the report: Shared's AttentionType has no `check_in_escalation` case). Also used by the likeness warning of
 * a published collection, whose subject is the collection.
 */
#[AsAttentionHandler(AttentionType::MissingConfirmation)]
final class CheckInEscalationHandler extends AbstractAttentionHandler
{
    protected function remedies(AttentionItemView $item): array
    {
        return 'participation' === $item->subjectType
            ? [AttentionAction::link('open_participation', 'participation', $item->subjectId)]
            : [AttentionAction::link('review_collection', $item->subjectType, $item->subjectId)];
    }
}
