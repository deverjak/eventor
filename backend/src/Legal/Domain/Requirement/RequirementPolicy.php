<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Requirement;

use Eventor\Legal\Contract\RequirementType;
use Eventor\Legal\Domain\Exception\InvalidRequirement;

/**
 * Rejects invalid requirement configurations at save time (LEG-06, ADR 012). A voluntary consent (`consent`,
 * `likeness`) is never a condition of the registration: it can be neither mandatory nor pre-checked, and it must be
 * revocable (LEG-09).
 */
final class RequirementPolicy
{
    public static function isVoluntary(RequirementType $type): bool
    {
        return RequirementType::Consent === $type || RequirementType::Likeness === $type;
    }

    /**
     * @return list<array{path: string, message: string, code: string}>
     */
    public static function violations(RequirementType $type, bool $mandatory, bool $revocable, bool $preChecked): array
    {
        if (!self::isVoluntary($type)) {
            return [];
        }

        $violations = [];
        if ($mandatory) {
            $violations[] = ['path' => 'mandatory', 'message' => 'A voluntary consent cannot be a condition of the registration.', 'code' => 'consent_not_mandatory'];
        }
        if ($preChecked) {
            $violations[] = ['path' => 'preChecked', 'message' => 'A voluntary consent cannot be pre-checked.', 'code' => 'consent_not_prechecked'];
        }
        if (!$revocable) {
            $violations[] = ['path' => 'revocable', 'message' => 'A voluntary consent must be revocable.', 'code' => 'consent_revocable'];
        }

        return $violations;
    }

    /**
     * @throws InvalidRequirement
     */
    public static function assertValid(RequirementType $type, bool $mandatory, bool $revocable, bool $preChecked = false): void
    {
        $violations = self::violations($type, $mandatory, $revocable, $preChecked);
        if ([] !== $violations) {
            throw new InvalidRequirement($violations);
        }
    }
}
