<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query\View;

/**
 * A message template as the organizer sees it: its own text (`source` custom, with an id) or the built-in one.
 */
final readonly class TemplateView
{
    public function __construct(
        public ?string $id,
        public string $key,
        public string $locale,
        public string $subject,
        public string $body,
        public string $source,
        public ?\DateTimeImmutable $updatedAt,
    ) {}
}
