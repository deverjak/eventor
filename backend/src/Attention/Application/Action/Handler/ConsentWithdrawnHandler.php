<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Shared\Application\Attention\AttentionType;

/**
 * A revocable consent was withdrawn (S16): take note; for a likeness consent review the photos of the person.
 */
#[AsAttentionHandler(AttentionType::ConsentWithdrawn)]
final class ConsentWithdrawnHandler extends AbstractAttentionHandler
{
    protected function remedies(AttentionItemView $item): array
    {
        $actions = [];
        $participation = self::payloadString($item, 'participationId');
        if (null !== $participation) {
            $actions[] = AttentionAction::link('review_participation', 'participation', $participation);
        }
        if ('likeness' === ($item->payload['requirementType'] ?? null)) {
            $actions[] = AttentionAction::link('review_photos', 'confirmation', $item->subjectId);
        }

        return $actions;
    }
}
