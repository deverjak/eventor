<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Audit;

enum ActorType: string
{
    case User = 'USER';
    case System = 'SYSTEM';
    case ParticipantToken = 'PARTICIPANT_TOKEN';
    case Device = 'DEVICE';
    case Pairrot = 'PAIRROT';
}
