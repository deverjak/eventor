<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Doctrine;

use Eventor\Registration\Domain\Participation\RegistrationCodes;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Eight characters from an alphabet without 0/O/1/I/L (about 40 bits): readable aloud at the desk, impossible to guess
 * a neighbour's code. A clash is caught by the unique index `(organizer_id, registration_code)` and is astronomically rare.
 */
#[AsAlias(RegistrationCodes::class)]
final readonly class RandomRegistrationCodes implements RegistrationCodes
{
    private const string ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function next(): string
    {
        $code = '';
        $length = \strlen(self::ALPHABET);
        for ($i = 0; $i < 8; ++$i) {
            $code .= self::ALPHABET[random_int(0, $length - 1)];
        }

        return $code;
    }
}
