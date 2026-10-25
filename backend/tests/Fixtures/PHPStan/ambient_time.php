<?php

declare(strict_types=1);

// Input of NoNativeDateTimeRuleTest: not production code, excluded from the normal PHPStan run.
function ambientTime(): void
{
    $a = new DateTimeImmutable();
    $b = new DateTime('now');
    $c = new DateTimeImmutable('+1 day');
    $d = time();
    $e = date('Y-m-d');
    $f = strtotime('tomorrow');
}

function allowed(string $fromDatabase, DateTimeImmutable $given): void
{
    $a = new DateTimeImmutable($fromDatabase);
    $b = new DateTimeImmutable('2026-11-01T12:00:00+01:00');
    $c = DateTimeImmutable::createFromInterface($given);
    $d = new DateTimeZone('Europe/Prague');
}
