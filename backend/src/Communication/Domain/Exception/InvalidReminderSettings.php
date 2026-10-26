<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'invalid_reminder_settings', status: 422)]
final class InvalidReminderSettings extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
