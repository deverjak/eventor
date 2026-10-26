<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'template_exists', status: 409)]
final class TemplateKeyTaken extends DomainException
{
    public static function for(string $key, string $locale): self
    {
        return new self(\sprintf('The organizer already has a "%s" template in "%s". Change it instead.', $key, $locale));
    }
}
