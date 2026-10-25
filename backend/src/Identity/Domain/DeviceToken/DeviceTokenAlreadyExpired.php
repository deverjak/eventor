<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\DeviceToken;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'device_token_expiry_in_past', status: 422)]
final class DeviceTokenAlreadyExpired extends DomainException
{
    public static function create(): self
    {
        return new self('The expiry of a device token must be in the future.');
    }
}
