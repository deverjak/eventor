<?php

declare(strict_types=1);

namespace Eventor\Content\Contract;

/**
 * What a post-event material links to (POST-04).
 */
enum MaterialType: string
{
    case Results = 'results';
    case Video = 'video';
    case Document = 'document';
    case Partners = 'partners';
    case Other = 'other';
}
