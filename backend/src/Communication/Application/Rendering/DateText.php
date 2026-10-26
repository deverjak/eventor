<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Rendering;

use Eventor\Communication\Contract\MessageLocale;

/**
 * Dates as people read them in a message, in the time zone of the event ("sobota 21. listopadu 2026 8:00").
 */
final readonly class DateText
{
    public function dateTime(\DateTimeInterface $at, string $timezone, MessageLocale $locale): string
    {
        return $this->format($at, $timezone, $locale, MessageLocale::Czech === $locale ? 'EEEE d. MMMM y H:mm' : 'EEEE d MMMM y, HH:mm');
    }

    public function date(\DateTimeInterface $at, string $timezone, MessageLocale $locale): string
    {
        return $this->format($at, $timezone, $locale, MessageLocale::Czech === $locale ? 'EEEE d. MMMM y' : 'EEEE d MMMM y');
    }

    private function format(\DateTimeInterface $at, string $timezone, MessageLocale $locale, string $pattern): string
    {
        $formatter = new \IntlDateFormatter(MessageLocale::Czech === $locale ? 'cs_CZ' : 'en_GB', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $timezone, \IntlDateFormatter::GREGORIAN, $pattern);
        $text = $formatter->format($at);

        return false === $text ? $at->format('Y-m-d H:i') : $text;
    }
}
