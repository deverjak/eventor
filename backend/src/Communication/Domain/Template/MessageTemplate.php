<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Template;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Event\TemplateChanged;
use Eventor\Communication\Domain\Exception\InvalidTemplate;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * An organizer's own wording of one message in one language (COM-08). Without a row the built-in text applies. The text
 * is a sandboxed Twig template over the variables of the read models; the application layer compiles it before it is
 * stored, the aggregate guards the shape (non-blank, bounded).
 */
#[ORM\Entity]
#[ORM\Table(name: 'message_template', schema: 'communication')]
#[ORM\UniqueConstraint(name: 'message_template_unique', columns: ['organizer_id', 'template_key', 'locale'])]
class MessageTemplate implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    public const int MAX_SUBJECT = 300;
    public const int MAX_BODY = 20000;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'message_template_id')]
        private MessageTemplateId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(name: 'template_key', type: 'string', length: 64, enumType: TemplateKey::class)]
        private TemplateKey $key,
        #[ORM\Column(type: 'string', length: 5, enumType: MessageLocale::class)]
        private MessageLocale $locale,
        #[ORM\Column(type: 'string', length: 300)]
        private string $subject,
        #[ORM\Column(type: 'text')]
        private string $body,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(MessageTemplateId $id, OrganizerId $organizerId, TemplateKey $key, MessageLocale $locale, string $subject, string $body, \DateTimeImmutable $now): self
    {
        self::guard($subject, $body);
        $template = new self($id, $organizerId, $key, $locale, trim($subject), $body, $now, $now);
        $template->record(new TemplateChanged($organizerId, $id, $key, $locale, 'created'));

        return $template;
    }

    public function change(string $subject, string $body, \DateTimeImmutable $now): void
    {
        self::guard($subject, $body);
        $this->subject = trim($subject);
        $this->body = $body;
        $this->updatedAt = $now;
        $this->record(new TemplateChanged($this->organizerId, $this->id, $this->key, $this->locale, 'changed'));
    }

    public function remove(): void
    {
        $this->record(new TemplateChanged($this->organizerId, $this->id, $this->key, $this->locale, 'removed'));
    }

    private static function guard(string $subject, string $body): void
    {
        if ('' === trim($subject) || '' === trim($body)) {
            throw InvalidTemplate::because('A template needs a subject and a body.');
        }
        if (mb_strlen($subject) > self::MAX_SUBJECT || mb_strlen($body) > self::MAX_BODY) {
            throw InvalidTemplate::because(\sprintf('The subject has at most %d and the body at most %d characters.', self::MAX_SUBJECT, self::MAX_BODY));
        }
    }

    public function id(): MessageTemplateId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function key(): TemplateKey
    {
        return $this->key;
    }

    public function locale(): MessageLocale
    {
        return $this->locale;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
