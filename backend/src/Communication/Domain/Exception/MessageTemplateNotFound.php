<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'message_template_not_found', status: 404)]
final class MessageTemplateNotFound extends DomainException
{
    public static function forId(string $id): self
    {
        return new self(\sprintf('Message template "%s" was not found.', $id));
    }
}
