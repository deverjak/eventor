<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Eventor\Catalog\Contract\Exception\FormVersionNotFound;
use Eventor\Catalog\Contract\FormFieldView;
use Eventor\Catalog\Contract\FormSchemaProvider;
use Eventor\Catalog\Contract\FormSchemaView;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The dynamic form as data for Registration (public form endpoint, preview): only published versions.
 */
#[AsAlias(FormSchemaProvider::class)]
final readonly class CatalogFormSchemaProvider implements FormSchemaProvider
{
    public function __construct(private PublishedFormReader $forms) {}

    public function currentVersionId(OrganizerId $organizer, EventId $event): FormVersionId
    {
        return $this->forms->current($organizer, $event)->id;
    }

    /**
     * @throws FormVersionNotFound
     */
    public function schema(OrganizerId $organizer, FormVersionId $version): FormSchemaView
    {
        $stored = $this->forms->version($organizer, $version);

        return new FormSchemaView(
            $stored->id,
            $stored->eventId,
            $stored->number,
            $stored->publishedAt,
            $stored->schemaSha256,
            array_map(
                static fn($field): FormFieldView => new FormFieldView(
                    $field->key,
                    $field->label,
                    $field->type->value,
                    $field->scope,
                    $field->required,
                    $field->semantic,
                    array_map(ActivityId::fromString(...), $field->activityIds),
                    array_map(VariantId::fromString(...), $field->variantIds),
                ),
                $stored->schema->fields,
            ),
        );
    }
}
