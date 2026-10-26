<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Test decorator of `MailerInterface` (test environment only, chapter 09 "RecordingMailer"): remembers every message
 * that was accepted and can simulate an outage of the mail service (S09, COM-07), permanently or for the next N sends.
 */
final class RecordingMailer implements MailerInterface
{
    /** @var list<RawMessage> */
    private array $sent = [];
    private bool $down = false;
    private int $failNext = 0;
    private int $refused = 0;

    public function __construct(private readonly MailerInterface $inner) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($this->down || $this->failNext > 0) {
            if ($this->failNext > 0) {
                --$this->failNext;
            }
            ++$this->refused;

            throw new TransportException('Connection to "smtp4dev:25" could not be established: connection refused.');
        }

        $this->inner->send($message, $envelope);
        $this->sent[] = $message;
    }

    /** The whole mail service is down until `outage(false)`. */
    public function outage(bool $down = true): void
    {
        $this->down = $down;
    }

    /** The next `$times` sends fail, then the service is back. */
    public function failNext(int $times): void
    {
        $this->failNext = $times;
    }

    public function refusedCount(): int
    {
        return $this->refused;
    }

    /**
     * @return list<Email>
     */
    public function emails(): array
    {
        return array_values(array_filter($this->sent, static fn(RawMessage $m): bool => $m instanceof Email));
    }

    /**
     * @return list<Email>
     */
    public function emailsTo(string $address): array
    {
        return array_values(array_filter($this->emails(), static function (Email $mail) use ($address): bool {
            foreach ($mail->getTo() as $to) {
                if (0 === strcasecmp($to->getAddress(), $address)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @return list<Email> the messages that carry this delivery's header
     */
    public function emailsOf(string $deliveryId): array
    {
        return array_values(array_filter($this->emails(), static fn(Email $mail): bool => $mail->getHeaders()->get(MailerMessageSender::DELIVERY_HEADER)?->getBodyAsString() === $deliveryId));
    }

    public function reset(): void
    {
        $this->sent = [];
        $this->down = false;
        $this->failNext = 0;
        $this->refused = 0;
    }
}
