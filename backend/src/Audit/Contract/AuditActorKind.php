<?php

declare(strict_types=1);

namespace Eventor\Audit\Contract;

/**
 * The kinds of actor an audit trail can be filtered by (AUD-03). `Pairrot` is the payment provider acting through
 * its webhook; clients that do not care about it can treat it as a system actor.
 */
enum AuditActorKind: string
{
    case User = 'user';
    case System = 'system';
    case Participant = 'participant';
    case Device = 'device';
    case Pairrot = 'pairrot';

    /**
     * The value stored in `audit_entry.actor_type`.
     */
    public function storedValue(): string
    {
        return match ($this) {
            self::User => 'USER',
            self::System => 'SYSTEM',
            self::Participant => 'PARTICIPANT_TOKEN',
            self::Device => 'DEVICE',
            self::Pairrot => 'PAIRROT',
        };
    }
}
