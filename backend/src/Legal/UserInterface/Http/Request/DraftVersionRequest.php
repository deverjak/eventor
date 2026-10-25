<?php

declare(strict_types=1);

namespace Eventor\Legal\UserInterface\Http\Request;

use Eventor\Legal\Application\DraftVersion\DraftDocumentVersion;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class DraftVersionRequest
{
    public function __construct(
        #[Assert\Choice(choices: [DraftDocumentVersion::TEXT, DraftDocumentVersion::PDF, DraftDocumentVersion::EXTERNAL_LINK])]
        public string $kind,
        public ?string $text = null,
        public ?string $pdfBase64 = null,
        #[Assert\Length(max: 2000)]
        public ?string $url = null,
    ) {}
}
