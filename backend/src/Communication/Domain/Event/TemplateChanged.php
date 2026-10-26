<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Event;

use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Template\MessageTemplateId;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: an organizer created, changed or removed its override of a message template. Audited (the text is
 * the organizer's own and contains no personal data).
 */
final readonly class TemplateChanged implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public MessageTemplateId $templateId,
        public TemplateKey $key,
        public MessageLocale $locale,
        public string $change,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('message_template', $this->templateId->toString(), 'communication.template_'.$this->change, $this->organizerId, after: [
            'key' => $this->key->value,
            'locale' => $this->locale->value,
        ]);
    }
}
