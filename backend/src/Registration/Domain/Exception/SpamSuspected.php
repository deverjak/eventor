<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The public order form tripped the honeypot or the minimum fill time (API-03).
 */
#[AsProblem(code: 'spam_suspected', status: 422)]
final class SpamSuspected extends DomainException
{
    public static function honeypot(): self
    {
        return new self('The form was rejected as automated.');
    }

    public static function tooFast(): self
    {
        return new self('The form was submitted too quickly. Please try again.');
    }
}
