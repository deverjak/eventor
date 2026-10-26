<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query;

use Eventor\Communication\Application\Query\View\TemplatePreviewView;
use Eventor\Communication\Application\Rendering\SandboxedTemplateRenderer;
use Eventor\Communication\Application\Rendering\TemplateCatalog;
use Eventor\Communication\Application\Rendering\TemplateVariables;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Exception\InvalidTemplate;
use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Preview of a message (COM-03, COM-08): the text typed so far, or the one that applies to a key, rendered with sample
 * data. A text the sandbox refuses is reported the way saving it would.
 */
final readonly class TemplatePreviewQuery
{
    public function __construct(
        private SandboxedTemplateRenderer $renderer,
        private TemplateCatalog $templates,
        private OrganizerSettings $organizers,
    ) {}

    /**
     * @throws InvalidTemplate
     */
    public function preview(OrganizerId $organizer, ?TemplateKey $key, MessageLocale $locale, ?string $subject, ?string $body): TemplatePreviewView
    {
        if ((null === $subject || null === $body) && null === $key) {
            throw InvalidTemplate::because('Give the subject and the body, or the key of the template to preview.');
        }
        if (null !== $key) {
            $template = $this->templates->resolve($key, $locale);
            $subject ??= $template->subject;
            $body ??= $template->body;
        }
        \assert(null !== $subject && null !== $body);

        $contact = $this->organizers->contact($organizer);
        $variables = array_replace_recursive(TemplateVariables::blank(), TemplateVariables::sample($contact->name, $contact->contactEmail));
        /** @var array<string, mixed> $variables */

        return new TemplatePreviewView($this->renderer->renderLine($subject, $variables), $this->renderer->render($body, $variables));
    }
}
