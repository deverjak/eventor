<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publish\Rule;

use Eventor\Catalog\Application\Publish\PublishCandidate;
use Eventor\Catalog\Application\Publish\PublishRule;
use Eventor\Catalog\Contract\SemanticField;
use Eventor\Catalog\Domain\Form\SelectionContext;

/**
 * An activity for minors needs the legal guardian in the form (LEG-11): name and e-mail, applicable to that activity.
 */
final readonly class MinorsOnlyNeedsGuardianFields implements PublishRule
{
    private const array REQUIRED = [SemanticField::GuardianFirstName, SemanticField::GuardianLastName, SemanticField::GuardianEmail];

    public function check(PublishCandidate $candidate): array
    {
        $violations = [];
        foreach ($candidate->activities as $activity) {
            if (!$activity->minorsOnly()) {
                continue;
            }
            $context = new SelectionContext([$activity->id()->toString()], []);
            foreach (self::REQUIRED as $semantic) {
                $found = false;
                foreach ($candidate->form->fields as $field) {
                    if ($field->semantic === $semantic && $field->isBoundTo($context)) {
                        $found = true;
                    }
                }
                if (!$found) {
                    $violations[] = [
                        'path' => 'form',
                        'message' => \sprintf('The activity "%s" is for minors, so the form needs the field "%s" for it.', $activity->name(), $semantic->value),
                        'code' => 'guardian_field_missing',
                    ];
                }
            }
        }

        return $violations;
    }
}
