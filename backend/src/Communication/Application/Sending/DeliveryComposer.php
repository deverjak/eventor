<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Sending;

use Eventor\Communication\Application\Port\QrCodeRenderer;
use Eventor\Communication\Application\Rendering\SandboxedTemplateRenderer;
use Eventor\Communication\Application\Rendering\TemplateCatalog;
use Eventor\Communication\Domain\Campaign\CampaignRepository;
use Eventor\Communication\Domain\Delivery\Delivery;
use Eventor\Communication\Domain\Exception\InvalidTemplate;
use Eventor\Communication\Domain\Sending\OutgoingMessage;

/**
 * Writes the message of a delivery: the wording (a campaign's own text or the template of the trigger), the variables
 * from the read models, the QR image of a payment.
 */
final readonly class DeliveryComposer
{
    public function __construct(
        private MessageContextFactory $contexts,
        private TemplateCatalog $templates,
        private CampaignRepository $campaigns,
        private SandboxedTemplateRenderer $renderer,
        private QrCodeRenderer $qr,
    ) {}

    /**
     * @throws MessageNotComposable
     */
    public function compose(Delivery $delivery): OutgoingMessage
    {
        $context = $this->contexts->build($delivery);

        $campaign = null;
        if (null !== $delivery->campaignId()) {
            $campaign = $this->campaigns->find($delivery->campaignId()) ?? throw new MessageNotComposable('campaign_gone');
        }
        try {
            if (null === $campaign) {
                $template = $this->templates->resolve($delivery->templateKey(), $context->locale);
                $subject = $this->renderer->renderLine($template->subject, $context->variables);
                $body = $this->renderer->render($template->body, $context->variables);
            } else {
                $subject = $this->renderer->renderLine($campaign->subject(), $context->variables);
                $body = $this->renderer->render($campaign->body(), $context->variables);
            }
        } catch (InvalidTemplate $e) {
            throw new MessageNotComposable('template_error', $e);
        }

        $organizer = $context->variables['organizer'];
        \assert(\is_array($organizer));

        return new OutgoingMessage(
            $delivery->id(),
            $delivery->organizerId(),
            $delivery->purpose(),
            \is_string($organizer['name'] ?? null) ? $organizer['name'] : '',
            \is_string($organizer['contactEmail'] ?? null) ? $organizer['contactEmail'] : '',
            $context->recipientEmail,
            $context->recipientName,
            $subject,
            $body,
            null === $context->qrPayload ? null : $this->qr->png($context->qrPayload),
            $context->consentPersons,
        );
    }
}
