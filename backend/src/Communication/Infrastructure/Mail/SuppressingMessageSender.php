<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Mail;

use Eventor\Communication\Application\Audience\CampaignRecipients;
use Eventor\Communication\Application\Suppression\AddSuppressionHandler;
use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\SuppressionScope;
use Eventor\Communication\Domain\Sending\MessageSender;
use Eventor\Communication\Domain\Sending\OutgoingMessage;
use Eventor\Communication\Domain\Sending\SendOutcome;
use Eventor\Communication\Domain\Suppression\SuppressionRepository;
use Eventor\Legal\Contract\ConsentQuery;
use Eventor\Legal\Contract\RequirementType;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * PRV-07, COM-09: the last gate before the channel. A marketing message needs a consent that is still granted at this
 * moment (a withdrawal wins over everything planned earlier), and no message goes to an address on the suppression
 * list (`all`: a hard bounce; `marketing`: an opt-out). A refused message is not an error: the delivery is `skipped`.
 */
#[AsDecorator(decorates: MessageSender::class)]
final readonly class SuppressingMessageSender implements MessageSender
{
    public function __construct(
        #[AutowireDecorated]
        private MessageSender $inner,
        private SuppressionRepository $suppressions,
        private ConsentQuery $consents,
    ) {}

    public function send(OutgoingMessage $message): SendOutcome
    {
        $hash = AddSuppressionHandler::hash($message->toEmail);
        if (null !== $this->suppressions->findByHash($hash, SuppressionScope::All)) {
            return SendOutcome::suppressed('suppressed');
        }

        if (CampaignPurpose::Marketing === $message->purpose) {
            if (null !== $this->suppressions->findByHash($hash, SuppressionScope::Marketing)) {
                return SendOutcome::suppressed('suppressed');
            }
            $granted = [] === $message->consentPersons
                ? []
                : $this->consents->grantedAmong($message->organizerId, RequirementType::Consent, CampaignRecipients::MARKETING_PURPOSE, $message->consentPersons);
            if ([] === $granted) {
                return SendOutcome::suppressed('consent_withdrawn');
            }
        }

        return $this->inner->send($message);
    }
}
