<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Constraint;

use Psr\Clock\ClockInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class MinimumAgeValidator extends ConstraintValidator
{
    public function __construct(private readonly ClockInterface $clock) {}

    /** Whole years between the birth date and the reference date (the birthday counts as the day of the new age). */
    public static function ageOn(\DateTimeImmutable $birthDate, \DateTimeImmutable $reference): int
    {
        return $birthDate->diff($reference)->invert ? -1 : $birthDate->diff($reference)->y;
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MinimumAge) {
            throw new UnexpectedTypeException($constraint, MinimumAge::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        $birth = \is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (false === $birth) {
            return; // the Date constraint reports the format
        }
        $reference = null === $constraint->referenceDate
            ? \DateTimeImmutable::createFromInterface($this->clock->now())
            : \DateTimeImmutable::createFromFormat('!Y-m-d', $constraint->referenceDate);
        if (false === $reference) {
            return;
        }
        if (self::ageOn($birth, $reference) < $constraint->years) {
            $this->context->buildViolation($constraint->message)->setParameter('{{ years }}', (string) $constraint->years)->addViolation();
        }
    }
}
