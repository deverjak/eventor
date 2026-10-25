<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\PublishVersion;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Publishes a draft: it becomes the current, immutable version. `requiresReconfirmation` (LEG-08) asks everybody who
 * confirmed an older version to confirm again; without it the change is informative only (S14).
 */
final readonly class PublishDocumentVersion
{
    public function __construct(
        #[Assert\Uuid]
        public string $documentId,
        #[Assert\Uuid]
        public string $versionId,
        public bool $requiresReconfirmation = false,
    ) {}
}
