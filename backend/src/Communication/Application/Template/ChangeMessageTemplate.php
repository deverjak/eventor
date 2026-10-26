<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Template;

final readonly class ChangeMessageTemplate
{
    public function __construct(
        public string $templateId,
        public string $subject,
        public string $body,
    ) {}
}
