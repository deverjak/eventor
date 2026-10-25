<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\LegalRequirementId;

final class LegalRequirementIdType extends AbstractIdType
{
    public const string NAME = 'legal_requirement_id';

    protected function idClass(): string
    {
        return LegalRequirementId::class;
    }
}
