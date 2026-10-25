<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\InvitationId;

final class InvitationIdType extends AbstractIdType
{
    public const string NAME = 'invitation_id';

    protected function idClass(): string
    {
        return InvitationId::class;
    }
}
