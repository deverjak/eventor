<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\Validation;

use Eventor\Legal\Contract\RequirementType;
use Eventor\Legal\Domain\Requirement\RequirementPolicy;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NoPrecheckedConsentValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoPrecheckedConsent) {
            throw new UnexpectedTypeException($constraint, NoPrecheckedConsent::class);
        }
        if (!\is_object($value)) {
            return;
        }

        $type = $this->read($value, 'type');
        if (!$type instanceof RequirementType) {
            return;
        }
        $revocable = $this->read($value, 'revocable');
        $revocable = \is_bool($revocable) ? $revocable : RequirementPolicy::isVoluntary($type);

        foreach (RequirementPolicy::violations($type, true === $this->read($value, 'mandatory'), $revocable, true === $this->read($value, 'preChecked')) as $violation) {
            $this->context->buildViolation($violation['message'])
                ->atPath($violation['path'])
                ->setCode($violation['code'])
                ->addViolation();
        }
    }

    private function read(object $object, string $property): mixed
    {
        return property_exists($object, $property) ? (new \ReflectionProperty($object, $property))->getValue($object) : null;
    }
}
