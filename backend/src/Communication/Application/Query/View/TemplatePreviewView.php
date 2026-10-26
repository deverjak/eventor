<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query\View;

/**
 * A template rendered with sample data.
 */
final readonly class TemplatePreviewView
{
    public function __construct(
        public string $subject,
        public string $body,
    ) {}
}
