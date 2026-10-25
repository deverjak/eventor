<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\MembershipId;

final class MembershipIdType extends AbstractIdType
{
    public const string NAME = 'membership_id';

    protected function idClass(): string
    {
        return MembershipId::class;
    }
}
