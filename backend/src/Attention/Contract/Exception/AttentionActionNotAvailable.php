<?php

declare(strict_types=1);

namespace Eventor\Attention\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The action is unknown for this item, or the item is in a state that does not allow it (already resolved).
 */
#[AsProblem(code: 'attention_action_not_available', status: 422)]
final class AttentionActionNotAvailable extends DomainException
{
    public static function for(string $action, string $itemState): self
    {
        return new self(\sprintf('The action "%s" is not available for an item that is %s.', $action, $itemState));
    }
}
