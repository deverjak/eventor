<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * Structure that registrations may already depend on (removing an activity or a variant, changing a configured
 * limit without the capacity endpoint) can only be changed while the event is a draft.
 */
#[AsProblem(code: 'event_not_draft', status: 409)]
final class EventNotDraft extends DomainException
{
    public static function for(string $what): self
    {
        return new self(\sprintf('%s is only possible while the event is a draft.', $what));
    }
}
