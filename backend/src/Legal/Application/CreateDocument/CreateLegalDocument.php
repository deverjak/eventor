<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\CreateDocument;

use Eventor\Legal\Contract\DocumentLevel;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Creates an empty legal document at a level (organizer, event or activity). `scopeId` is the organizer, event or
 * activity id according to `level`. `replacesDocumentId` makes it replace a document of a higher level (LEG-02).
 */
final readonly class CreateLegalDocument
{
    public function __construct(
        public DocumentLevel $level,
        #[Assert\Uuid]
        public ?string $scopeId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $title,
        #[Assert\Uuid]
        public ?string $replacesDocumentId = null,
    ) {}
}
