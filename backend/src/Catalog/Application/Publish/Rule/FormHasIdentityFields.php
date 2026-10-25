<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publish\Rule;

use Eventor\Catalog\Application\Publish\PublishCandidate;
use Eventor\Catalog\Application\Publish\PublishRule;
use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\SemanticField;

/**
 * Registrations create people: the form must ask each participant for the first and last name (FRM-04).
 */
final readonly class FormHasIdentityFields implements PublishRule
{
    public function check(PublishCandidate $candidate): array
    {
        $violations = [];
        foreach ([SemanticField::FirstName, SemanticField::LastName] as $semantic) {
            $found = false;
            foreach ($candidate->form->fields as $field) {
                if ($field->semantic === $semantic && $field->required && \in_array($field->scope, [AnswerScope::Person, AnswerScope::Participation], true)) {
                    $found = true;
                }
            }
            if (!$found) {
                $violations[] = ['path' => 'form', 'message' => \sprintf('The form needs a required per-person field with the meaning "%s".', $semantic->value), 'code' => 'identity_field_missing'];
            }
        }

        return $violations;
    }
}
