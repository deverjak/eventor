<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract;

/**
 * Languages of messages. A recipient with another locale gets Czech.
 */
enum MessageLocale: string
{
    case Czech = 'cs';
    case English = 'en';

    public static function fromContact(string $locale): self
    {
        return self::tryFrom(strtolower(substr($locale, 0, 2))) ?? self::Czech;
    }
}
