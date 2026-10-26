<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\AnswerSetInput;
use Eventor\Catalog\Contract\Exception\FormValidationFailed;
use Eventor\Catalog\Contract\FormSelection;
use Eventor\Catalog\Contract\FormSubmission;
use Eventor\Catalog\Contract\FormValidator;
use Eventor\Catalog\Contract\FormViolation;
use Eventor\Catalog\Contract\ValidatedAnswerSet;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * Judges the answers of a request against the form version (FRM-05, FRM-07). The fields that apply to a person depend
 * on what that person registers for (the guardian fields exist only for the children's activity), so every person is
 * validated with the selection of their own items, the order contact with the selection of the whole order. All
 * violations of all sets come back in one `form_invalid` problem, with the person's reference in the path.
 */
final readonly class SubmissionValidator
{
    public const string ORDER_REF = 'order';

    public function __construct(private FormValidator $forms) {}

    /**
     * @param list<OrderItemInput> $items
     * @param list<PersonInput>    $persons
     * @param array<string, mixed> $orderAnswers
     *
     * @throws FormValidationFailed
     */
    public function validate(OrganizerId $organizer, FormVersionId $version, array $items, array $persons, array $orderAnswers): ValidatedSubmission
    {
        $violations = [];
        $order = null;
        $validated = [];

        try {
            $order = $this->validateSet($organizer, $version, AnswerScope::Order, self::ORDER_REF, $orderAnswers, self::selection($items));
        } catch (FormValidationFailed $e) {
            array_push($violations, ...self::violationsOf($e));
        }

        foreach ($persons as $person) {
            $own = array_values(array_filter($items, static fn(OrderItemInput $item): bool => $item->personRef === $person->personRef));
            try {
                $validated[$person->personRef] = $this->validateSet($organizer, $version, AnswerScope::Person, $person->personRef, $person->answers, self::selection($own));
            } catch (FormValidationFailed $e) {
                array_push($violations, ...self::violationsOf($e));
            }
        }

        if ([] !== $violations || null === $order) {
            throw new FormValidationFailed($violations);
        }

        return new ValidatedSubmission($order, $validated);
    }

    /**
     * One person's answers on their own (data correction, REG-09).
     *
     * @param list<ActivityId>     $activities
     * @param list<VariantId>      $variants
     * @param array<string, mixed> $answers
     *
     * @throws FormValidationFailed
     */
    public function validatePerson(OrganizerId $organizer, FormVersionId $version, array $activities, array $variants, array $answers): ValidatedAnswerSet
    {
        return $this->validateSet($organizer, $version, AnswerScope::Person, 'person', $answers, new FormSelection($activities, $variants));
    }

    /**
     * The order contact on its own (data correction, REG-09).
     *
     * @param list<ActivityId>     $activities
     * @param list<VariantId>      $variants
     * @param array<string, mixed> $answers
     *
     * @throws FormValidationFailed
     */
    public function validateOrder(OrganizerId $organizer, FormVersionId $version, array $activities, array $variants, array $answers): ValidatedAnswerSet
    {
        return $this->validateSet($organizer, $version, AnswerScope::Order, self::ORDER_REF, $answers, new FormSelection($activities, $variants));
    }

    /**
     * @param array<string, mixed> $answers
     *
     * @throws FormValidationFailed
     */
    private function validateSet(OrganizerId $organizer, FormVersionId $version, AnswerScope $scope, string $ref, array $answers, FormSelection $selection): ValidatedAnswerSet
    {
        $result = $this->forms->validate($organizer, $version, new FormSubmission($selection, [new AnswerSetInput($scope, $ref, $answers)]));

        return $result->answerSets[0];
    }

    /**
     * @param list<OrderItemInput> $items
     */
    private static function selection(array $items): FormSelection
    {
        $activities = [];
        $variants = [];
        foreach ($items as $item) {
            $activities[$item->activityId->toString()] = $item->activityId;
            $variants[$item->variantId->toString()] = $item->variantId;
        }

        return new FormSelection(array_values($activities), array_values($variants));
    }

    /**
     * @return list<FormViolation>
     */
    private static function violationsOf(FormValidationFailed $e): array
    {
        return array_map(static fn(array $v): FormViolation => new FormViolation($v['path'], $v['code'], $v['message']), $e->violations());
    }
}
