<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Mail;

use Eventor\Communication\Domain\Sending\MessageSender;
use Eventor\Communication\Domain\Sending\OutgoingMessage;
use Eventor\Communication\Domain\Sending\SendFailed;
use Eventor\Communication\Domain\Sending\SendOutcome;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * E-mail through Symfony Mailer (chapter 07): called synchronously inside the processing of one delivery. COM-05: the
 * sender is the technical address of the platform with the organizer's name, `Reply-To` is the organizer's contact.
 * The `X-Eventor-Delivery` header ties the message to its delivery row (smtp4dev, bounces, support).
 */
#[AsAlias(MessageSender::class)]
final readonly class MailerMessageSender implements MessageSender
{
    public const string DELIVERY_HEADER = 'X-Eventor-Delivery';
    public const string QR_CONTENT_ID = 'payment-qr';

    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(COMMUNICATION_FROM_ADDRESS)%')]
        private string $fromAddress,
    ) {}

    public function send(OutgoingMessage $message): SendOutcome
    {
        $email = (new Email())
            ->from(new Address($this->fromAddress, trim($message->fromName.' přes Eventor')))
            ->to(new Address($message->toEmail, $message->toName))
            ->subject($message->subject)
            ->text($message->text)
            ->html(self::html($message->text, null !== $message->qrPng));
        if ('' !== $message->replyToEmail) {
            $email->replyTo(new Address($message->replyToEmail, $message->fromName));
        }
        $email->getHeaders()->addTextHeader(self::DELIVERY_HEADER, $message->deliveryId->toString());
        $messageId = $email->generateMessageId();
        $email->getHeaders()->addIdHeader('Message-ID', $messageId);
        if (null !== $message->qrPng) {
            $email->embed($message->qrPng, self::QR_CONTENT_ID, 'image/png');
        }

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new SendFailed(self::errorCode($e), $e);
        }

        return SendOutcome::sent($messageId);
    }

    /**
     * The text is plain; the HTML part is the same text escaped, with line breaks and links, plus the payment QR.
     */
    public static function html(string $text, bool $withQr): string
    {
        $escaped = htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $linked = (string) preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', $escaped);
        $qr = $withQr ? '<p><img src="cid:'.self::QR_CONTENT_ID.'" alt="QR platba" width="240" height="240"></p>' : '';

        return '<!doctype html><html><body style="font-family: sans-serif; line-height: 1.5">'.nl2br($linked).$qr.'</body></html>';
    }

    /**
     * A stable code without the recipient's address (`smtp_550`, `transport_error`): it is stored and published.
     */
    private static function errorCode(TransportExceptionInterface $e): string
    {
        return 1 === preg_match('/code "(\d{3})"/', $e->getMessage(), $match) ? 'smtp_'.$match[1] : 'transport_error';
    }
}
