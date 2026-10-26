<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Eligibility;

use Eventor\Registration\Contract\Exception\NotEligible;

/**
 * Conditions of participation (chapter 04, path 2, step 2): age at the reference date of the event against the limits
 * of the activity and of the variant. Pure: the offer's numbers come in as arguments, so the rule is testable without
 * a kernel. Categories (EVT-08) and team sizes (CAP-07) are P1.
 */
final class ParticipationEligibilityPolicy
{
    public const int ADULT_AGE = 18;

    /**
     * @throws NotEligible
     */
    public static function assertAge(?\DateTimeImmutable $birthDate, \DateTimeImmutable $reference, ?int $minAge, ?int $maxAge, string $label): void
    {
        if (null === $minAge && null === $maxAge) {
            return;
        }
        if (null === $birthDate) {
            throw NotEligible::because(\sprintf('The date of birth is required to register for "%s".', $label));
        }

        $age = self::ageAt($birthDate, $reference);
        if (null !== $minAge && $age < $minAge) {
            throw NotEligible::because(\sprintf('"%s" is for participants aged %d and over (age at the event: %d).', $label, $minAge, $age));
        }
        if (null !== $maxAge && $age > $maxAge) {
            throw NotEligible::because(\sprintf('"%s" is for participants up to %d years (age at the event: %d).', $label, $maxAge, $age));
        }
    }

    public static function ageAt(\DateTimeImmutable $birthDate, \DateTimeImmutable $reference): int
    {
        return 0 !== $birthDate->diff($reference)->invert ? 0 : $birthDate->diff($reference)->y;
    }

    /** A minor needs the confirmations of a guardian (LEG-11). Without a birth date the person is treated as an adult. */
    public static function isMinor(?\DateTimeImmutable $birthDate, \DateTimeImmutable $reference): bool
    {
        return null !== $birthDate && self::ageAt($birthDate, $reference) < self::ADULT_AGE;
    }
}
