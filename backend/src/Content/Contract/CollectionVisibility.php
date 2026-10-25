<?php

declare(strict_types=1);

namespace Eventor\Content\Contract;

/**
 * Who may open a collection (POST-02, POST-03).
 */
enum CollectionVisibility: string
{
    case Public = 'public';
    case LinkOnly = 'link_only';
    case Participants = 'participants';
}
