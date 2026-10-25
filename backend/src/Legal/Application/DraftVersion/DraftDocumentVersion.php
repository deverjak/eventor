<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\DraftVersion;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Uploads the content of the next version of a document as a draft (LEG-01): `text` (Markdown), `pdf` (base64) or
 * `external_link` (the page is downloaded now and an immutable copy is stored). A new draft replaces an unpublished one.
 */
final readonly class DraftDocumentVersion
{
    public const string TEXT = 'text';
    public const string PDF = 'pdf';
    public const string EXTERNAL_LINK = 'external_link';

    public function __construct(
        #[Assert\Uuid]
        public string $documentId,
        #[Assert\Choice(choices: [self::TEXT, self::PDF, self::EXTERNAL_LINK])]
        public string $kind,
        public ?string $text = null,
        public ?string $pdfBase64 = null,
        #[Assert\Url(requireTld: true)]
        #[Assert\Length(max: 2000)]
        public ?string $url = null,
    ) {}
}
