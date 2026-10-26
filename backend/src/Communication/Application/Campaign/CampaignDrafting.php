<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Communication\Application\Audience\AudienceSpecGuard;
use Eventor\Communication\Application\Rendering\SandboxedTemplateRenderer;
use Eventor\Communication\Application\Rendering\TemplateCatalog;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Exception\InvalidCampaign;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * What creating and editing a campaign have in common: the wording (own text, or the template of a key), the sandbox
 * check, the audience check, and that the event exists for this organizer.
 */
final readonly class CampaignDrafting
{
    public function __construct(
        private TemplateCatalog $templates,
        private SandboxedTemplateRenderer $renderer,
        private EventCatalog $events,
    ) {}

    /**
     * @return array{subject: string, body: string}
     *
     * @throws InvalidCampaign
     */
    public function wording(?TemplateKey $key, MessageLocale $locale, ?string $subject, ?string $body): array
    {
        if ((null === $subject || '' === trim($subject) || null === $body || '' === trim($body)) && null === $key) {
            throw InvalidCampaign::because('Give the subject and the body, or choose a template.');
        }
        if (null !== $key) {
            $template = $this->templates->resolve($key, $locale);
            $subject = null === $subject || '' === trim($subject) ? $template->subject : $subject;
            $body = null === $body || '' === trim($body) ? $template->body : $body;
        }
        \assert(null !== $subject && null !== $body);
        $this->renderer->assertValid($subject, $body);

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * @throws InvalidCampaign
     * @throws \Eventor\Catalog\Contract\Exception\EventNotFound
     */
    public function assertTarget(OrganizerId $organizer, EventId $event, CampaignAudienceSpec $audience): void
    {
        $this->events->summary($organizer, $event);
        AudienceSpecGuard::assertValid($audience);
    }
}
