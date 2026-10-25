<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

/**
 * EVT-11: a public event is listed on the organizer's page, an unlisted one is reachable only through its link.
 */
enum EventVisibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
}
