<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Sending;

use Eventor\Communication\Contract\MessageLocale;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * Everything a message is made of, read from the owners at send time (personal data: lives for one send only).
 */
final readonly class MessageContext
{
    /**
     * @param array<string, mixed> $variables template variables, the whitelist of `TemplateVariables`
     * @param list<PersonId> $consentPersons people whose consent a marketing message relies on
     */
    public function __construct(
        public array $variables,
        public string $recipientEmail,
        public string $recipientName,
        public MessageLocale $locale,
        public ?string $qrPayload,
        public array $consentPersons,
    ) {}
}
