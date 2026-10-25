<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

/**
 * Who sees a program item (PRG-02).
 */
enum ProgramVisibility: string
{
    case Public = 'public';
    case Participants = 'participants';
}
