<?php

declare(strict_types=1);

namespace Eventor\Reporting\Contract;

use Eventor\Reporting\Contract\Exception\ExportNotPermitted;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * CSV exports (ADM-07, PRV-02). The file is written to the exports storage and served by a presigned URL that expires
 * after 24 hours. Creating an export is audited. What an export may contain depends on the permissions of the actor.
 */
interface CsvExporter
{
    /**
     * @throws ExportNotPermitted
     */
    public function export(OrganizerId $organizer, ExportType $type, EventId $event): ExportedFile;
}
