<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

/**
 * Who has to confirm a requirement (LEG-11): the orderer once per order, each participant, or the legal guardian of a minor.
 */
enum Confirmer: string
{
    case Orderer = 'orderer';
    case EachParticipant = 'each_participant';
    case Guardian = 'guardian';
}
