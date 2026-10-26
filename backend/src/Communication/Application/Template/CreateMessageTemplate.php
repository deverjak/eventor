<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Template;

use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;

/**
 * An organizer's own wording of a message in one language (COM-08).
 */
final readonly class CreateMessageTemplate
{
    public function __construct(
        public TemplateKey $key,
        public MessageLocale $locale,
        public string $subject,
        public string $body,
    ) {}
}
