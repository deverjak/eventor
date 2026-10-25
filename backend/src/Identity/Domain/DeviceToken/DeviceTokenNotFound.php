<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\DeviceToken;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'device_token_not_found', status: 404)]
final class DeviceTokenNotFound extends DomainException
{
    public static function forRaw(string $id): self
    {
        return new self(\sprintf('Device token "%s" was not found.', mb_substr($id, 0, 64)));
    }

    public static function forId(DeviceTokenId $id): self
    {
        return new self(\sprintf('Device token "%s" was not found.', $id->toString()));
    }
}
