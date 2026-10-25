<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * The form editor's starting point: the draft, the latest published version and the list of published versions.
 */
final readonly class FormOverviewView
{
    /**
     * @param list<FormVersionSummaryView> $versions
     */
    public function __construct(
        public ?FormVersionView $draft,
        public ?FormVersionView $published,
        public array $versions,
    ) {}
}
