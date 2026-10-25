<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\FormVersionId;

final class FormVersionIdType extends AbstractIdType
{
    public const string NAME = 'form_version_id';

    protected function idClass(): string
    {
        return FormVersionId::class;
    }
}
