<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Doctrine;

use Eventor\Identity\Domain\Membership\MembershipId;
use Eventor\Shared\Infrastructure\Doctrine\AbstractIdType;

final class MembershipIdType extends AbstractIdType
{
    public const string NAME = 'membership_id';

    protected function idClass(): string
    {
        return MembershipId::class;
    }
}
