<?php

declare(strict_types=1);

namespace Eventor\Legal\UserInterface\Http\Request;

use Eventor\Legal\Contract\DocumentLevel;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateDocumentRequest
{
    public function __construct(
        public DocumentLevel $level,
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $title,
        /** Event or activity id; omitted for an organizer-level document. */
        #[Assert\Uuid]
        public ?string $scopeId = null,
        #[Assert\Uuid]
        public ?string $replacesDocumentId = null,
    ) {}
}
