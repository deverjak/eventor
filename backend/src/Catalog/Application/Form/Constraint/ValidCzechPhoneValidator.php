<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidCzechPhoneValidator extends ConstraintValidator
{
    /** @return non-empty-string|null the number as `+420XXXXXXXXX`, null when it is not a Czech number */
    public static function canonical(string $value): ?string
    {
        $compact = preg_replace('/[\s\-.]/', '', $value) ?? '';
        if (1 !== preg_match('/^(?:\+420|00420)?([1-9][0-9]{8})$/', $compact, $m)) {
            return null;
        }

        return '+420'.$m[1];
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidCzechPhone) {
            throw new UnexpectedTypeException($constraint, ValidCzechPhone::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!\is_string($value) || null === self::canonical($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
