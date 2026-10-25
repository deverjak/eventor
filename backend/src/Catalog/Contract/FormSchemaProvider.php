<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Contract\Exception\FormVersionNotFound;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The dynamic registration form as data (chapter 03, "Dynamic form"). Schemas are immutable snapshots per version.
 */
interface FormSchemaProvider
{
    /**
     * The version a new registration is validated against.
     *
     * @throws EventNotFound
     */
    public function currentVersionId(OrganizerId $organizer, EventId $event): FormVersionId;

    /**
     * @throws FormVersionNotFound
     */
    public function schema(OrganizerId $organizer, FormVersionId $version): FormSchemaView;
}
