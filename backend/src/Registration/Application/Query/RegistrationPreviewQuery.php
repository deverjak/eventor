<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query;

use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\FormFieldView;
use Eventor\Catalog\Contract\FormSchemaProvider;
use Eventor\Legal\Contract\ApplicableRequirement;
use Eventor\Legal\Contract\RegistrationSlot;
use Eventor\Legal\Contract\RequirementResolver;
use Eventor\Registration\Application\PlaceOrder\FormTimeTrap;
use Eventor\Registration\Application\Query\View\PreviewFieldView;
use Eventor\Registration\Application\Query\View\PreviewItemView;
use Eventor\Registration\Application\Query\View\PreviewRequirementView;
use Eventor\Registration\Application\Query\View\RegistrationPreviewView;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * FRM-11 (contracts decision 14: Registration already uses Catalog and Legal) and the public registration form: for a
 * chosen combination of activities and variants, which fields are asked of whom and which documents have to be confirmed.
 * Works on the published form version of the event.
 */
final readonly class RegistrationPreviewQuery
{
    public function __construct(
        private FormSchemaProvider $forms,
        private RequirementResolver $requirements,
        private FormTimeTrap $trap,
    ) {}

    /**
     * @param list<PreviewSelection> $selection
     */
    public function preview(OrganizerId $organizer, EventId $event, array $selection, bool $withFormToken): RegistrationPreviewView
    {
        $version = $this->forms->currentVersionId($organizer, $event);
        $schema = $this->forms->schema($organizer, $version);

        $activities = array_map(static fn(PreviewSelection $s): ActivityId => $s->activityId, $selection);
        $variants = array_map(static fn(PreviewSelection $s): VariantId => $s->variantId, $selection);
        $required = $this->requirements->forRegistration(
            $organizer,
            $event,
            array_map(static fn(PreviewSelection $s): RegistrationSlot => new RegistrationSlot($s->itemRef, $s->activityId, $s->variantId, $s->minor), $selection),
        );

        $items = array_map(fn(PreviewSelection $s): PreviewItemView => new PreviewItemView(
            $s->itemRef,
            $s->activityId,
            $s->variantId,
            $s->minor,
            $this->fields($schema->fields, AnswerScope::Person, [$s->activityId], [$s->variantId]),
            array_map(self::requirement(...), $required->perSlot[$s->itemRef] ?? []),
        ), $selection);

        return new RegistrationPreviewView(
            $event,
            $version,
            $this->fields($schema->fields, AnswerScope::Order, $activities, $variants),
            array_map(self::requirement(...), $required->orderLevel),
            $items,
            $withFormToken ? $this->trap->issue() : null,
        );
    }

    /**
     * @param list<FormFieldView> $fields
     * @param list<ActivityId>    $activities
     * @param list<VariantId>     $variants
     *
     * @return list<PreviewFieldView>
     */
    private function fields(array $fields, AnswerScope $scope, array $activities, array $variants): array
    {
        $activityIds = array_map(static fn(ActivityId $id): string => $id->toString(), $activities);
        $variantIds = array_map(static fn(VariantId $id): string => $id->toString(), $variants);

        $applicable = array_filter($fields, static function (FormFieldView $field) use ($scope, $activityIds, $variantIds): bool {
            if ($field->scope !== $scope) {
                return false;
            }
            $fieldActivities = array_map(static fn(ActivityId $id): string => $id->toString(), $field->activityIds);
            $fieldVariants = array_map(static fn(VariantId $id): string => $id->toString(), $field->variantIds);

            return ([] === $fieldActivities || [] !== array_intersect($fieldActivities, $activityIds))
                && ([] === $fieldVariants || [] !== array_intersect($fieldVariants, $variantIds));
        });

        return array_values(array_map(
            static fn(FormFieldView $f): PreviewFieldView => new PreviewFieldView($f->key, $f->label, $f->type, $f->scope->value, $f->required, $f->semantic?->value),
            $applicable,
        ));
    }

    private static function requirement(ApplicableRequirement $r): PreviewRequirementView
    {
        return new PreviewRequirementView($r->requirementId, $r->documentId, $r->versionId, $r->type, $r->documentTitle, $r->purpose, $r->confirmer, $r->mandatory, $r->revocable);
    }
}
