<?php

declare(strict_types=1);

namespace Eventor\Privacy\Contract;

/**
 * Kind of data subject request (PRV-03).
 */
enum DataSubjectRequestType: string
{
    case Access = 'access';
    case Export = 'export';
    case Erasure = 'erasure';
    case Rectification = 'rectification';
}
