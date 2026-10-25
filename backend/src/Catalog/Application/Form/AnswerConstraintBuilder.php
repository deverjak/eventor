<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Domain\Form\FormSchema;
use Eventor\Catalog\Domain\Form\SelectionContext;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;

/**
 * Builds the validator constraint of one answer set from the form version (FRM-07, chapter 09, "Validator"):
 * validation follows the schema, never what the frontend displayed. A field that does not apply to the selection (it
 * is bound to another activity or its condition is not met) is not part of the collection, so sending it is an
 * `unexpected_field` violation and requiring it is impossible.
 */
final readonly class AnswerConstraintBuilder
{
    public function __construct(private FieldTypeRegistry $types) {}

    public function forScope(FormSchema $schema, AnswerScope $scope, SelectionContext $context): Collection
    {
        $fields = [];
        foreach ($schema->fieldsFor($scope) as $field) {
            if (!$field->type->hasAnswer() || !$field->isApplicable($context)) {
                continue;
            }
            $constraints = $this->types->get($field->type)->constraints($field);
            $fields[$field->key] = $field->required ? new Required($constraints) : new Optional($constraints);
        }

        return new Collection(fields: $fields, allowExtraFields: false, allowMissingFields: false);
    }
}
