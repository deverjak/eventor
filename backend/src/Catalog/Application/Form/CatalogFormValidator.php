<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

use Eventor\Catalog\Application\Query\PublishedFormQuery;
use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\Exception\FormValidationFailed;
use Eventor\Catalog\Contract\FormSelection;
use Eventor\Catalog\Contract\FormSubmission;
use Eventor\Catalog\Contract\FormValidator;
use Eventor\Catalog\Contract\FormViolation;
use Eventor\Catalog\Contract\SemanticAnswers;
use Eventor\Catalog\Contract\SemanticField;
use Eventor\Catalog\Contract\ValidatedAnswers;
use Eventor\Catalog\Contract\ValidatedAnswerSet;
use Eventor\Catalog\Domain\Form\FormSchema;
use Eventor\Catalog\Domain\Form\SelectionContext;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Server-side validation of submitted answers against a published form version (FRM-05, FRM-07, FRM-09). The
 * constraints come from the schema (AnswerConstraintBuilder), conditions are evaluated here, never trusted from the
 * client. Valid answers are normalised by their field type and stored as `{ key: { t: type, v: value } }`; empty
 * answers are dropped so a blank field can never look answered.
 */
#[AsAlias(FormValidator::class)]
final readonly class CatalogFormValidator implements FormValidator
{
    public function __construct(
        private PublishedFormQuery $forms,
        private AnswerConstraintBuilder $builder,
        private FieldTypeRegistry $types,
        private ValidatorInterface $validator,
    ) {}

    public function validate(OrganizerId $organizer, FormVersionId $version, FormSubmission $submission): ValidatedAnswers
    {
        $schema = $this->forms->version($organizer, $version)->schema;

        $violations = [];
        $prepared = [];
        foreach ($submission->answerSets as $index => $set) {
            $answers = self::withoutEmpty($set->answers);
            $context = $this->context($submission->selection, $answers);
            $constraint = $this->builder->forScope($schema, $set->scope, $context);
            foreach ($this->validator->validate($answers, $constraint) as $violation) {
                $violations[] = $this->violation($set->subjectRef, $violation);
            }
            $prepared[$index] = [$answers, $context];
        }
        if ([] !== $violations) {
            throw new FormValidationFailed($violations);
        }

        $sets = [];
        foreach ($submission->answerSets as $index => $set) {
            [$answers] = $prepared[$index];
            $sets[] = $this->normalized($schema, $set->scope, $set->subjectRef, $answers, $prepared[$index][1]);
        }

        return new ValidatedAnswers($version, $sets);
    }

    public function missingRequiredFields(OrganizerId $organizer, FormVersionId $version, AnswerScope $scope, FormSelection $selection, array $answeredFieldKeys): array
    {
        $schema = $this->forms->version($organizer, $version)->schema;
        $context = $this->context($selection, []);

        $missing = [];
        foreach ($schema->fieldsFor($scope) as $field) {
            if (!$field->required || !$field->type->hasAnswer() || \in_array($field->key, $answeredFieldKeys, true)) {
                continue;
            }
            // A condition on another answer cannot be judged without the answers: such a field is not asked again.
            if ($field->isBoundTo($context) && (null === $field->condition || (!$field->condition->dependsOnAnswers() && $field->condition->isSatisfiedBy($context)))) {
                $missing[] = $field->key;
            }
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $answers
     */
    private function context(FormSelection $selection, array $answers): SelectionContext
    {
        return new SelectionContext(
            array_map(static fn($id): string => $id->toString(), $selection->activityIds),
            array_map(static fn($id): string => $id->toString(), $selection->variantIds),
            $answers,
        );
    }

    /**
     * @param array<string, mixed> $answers
     *
     * @return array<string, mixed>
     */
    private static function withoutEmpty(array $answers): array
    {
        $trimmed = array_map(static fn(mixed $value): mixed => \is_string($value) ? trim($value) : $value, $answers);

        return array_filter($trimmed, static fn(mixed $value): bool => '' !== $value && null !== $value && [] !== $value);
    }

    private function violation(string $subjectRef, ConstraintViolationInterface $violation): FormViolation
    {
        $property = $violation->getPropertyPath();
        $key = 1 === preg_match('/^\[([^\]]*)\]/', $property, $m) ? $m[1] : $property;
        $path = '' === $subjectRef ? $key : ('' === $key ? $subjectRef : $subjectRef.'.'.$key);

        $code = match ($violation->getCode()) {
            Collection::MISSING_FIELD_ERROR => 'required',
            Collection::NO_SUCH_FIELD_ERROR => 'unexpected_field',
            default => null === $violation->getConstraint() ? 'invalid' : strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', (new \ReflectionClass($violation->getConstraint()))->getShortName())),
        };
        $message = Collection::MISSING_FIELD_ERROR === $violation->getCode()
            ? 'This answer is required.'
            : (Collection::NO_SUCH_FIELD_ERROR === $violation->getCode() ? 'This field does not apply to the selection.' : (string) $violation->getMessage());

        return new FormViolation($path, $code, $message);
    }

    /**
     * @param array<string, mixed> $answers
     */
    private function normalized(FormSchema $schema, AnswerScope $scope, string $subjectRef, array $answers, SelectionContext $context): ValidatedAnswerSet
    {
        $stored = [];
        $semantic = [];
        foreach ($schema->fieldsFor($scope) as $field) {
            if (!\array_key_exists($field->key, $answers) || !$field->isApplicable($context)) {
                continue;
            }
            $value = $this->types->get($field->type)->normalize($field, $answers[$field->key]);
            $stored[$field->key] = ['t' => $field->type->value, 'v' => $value];
            if (null !== $field->semantic) {
                $semantic[$field->semantic->value] = $value;
            }
        }

        return new ValidatedAnswerSet($scope, $subjectRef, $stored, self::semantic($semantic));
    }

    /**
     * @param array<string, mixed> $values by SemanticField value
     */
    private static function semantic(array $values): SemanticAnswers
    {
        $text = static fn(SemanticField $field): ?string => \is_string($values[$field->value] ?? null) ? $values[$field->value] : null;
        $birth = $text(SemanticField::BirthDate);
        $birthDate = null === $birth ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $birth);

        return new SemanticAnswers(
            $text(SemanticField::FirstName),
            $text(SemanticField::LastName),
            false === $birthDate ? null : $birthDate,
            $text(SemanticField::Email),
            $text(SemanticField::Phone),
            $text(SemanticField::GuardianFirstName),
            $text(SemanticField::GuardianLastName),
            $text(SemanticField::GuardianEmail),
            $text(SemanticField::GuardianPhone),
        );
    }
}
