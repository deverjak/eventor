<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

use Eventor\Registration\Domain\Exception\SpamSuspected;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The antispam of the public order form (API-03, chapter 08): a honeypot field a human never fills and a minimum fill time.
 * The time comes from a signed token issued when the form (its preview) is loaded: `<issued-at>.<hmac>`. A form submitted
 * less than 3 seconds after that is a bot. A request without a token is judged by the honeypot alone, so API clients that
 * never loaded the form still work; a forged or tampered token is refused.
 */
final readonly class FormTimeTrap
{
    public const int MINIMUM_SECONDS = 3;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
        private ClockInterface $clock,
    ) {}

    public function issue(): string
    {
        $issued = (string) $this->clock->now()->getTimestamp();

        return $issued.'.'.$this->signature($issued);
    }

    /**
     * @throws SpamSuspected
     */
    public function assertHuman(?string $honeypot, ?string $token): void
    {
        if (null !== $honeypot && '' !== trim($honeypot)) {
            throw SpamSuspected::honeypot();
        }
        if (null === $token || '' === $token) {
            return;
        }

        $parts = explode('.', $token);
        if (2 !== \count($parts) || !ctype_digit($parts[0]) || !hash_equals($this->signature($parts[0]), $parts[1])) {
            throw SpamSuspected::honeypot();
        }
        if ($this->clock->now()->getTimestamp() - (int) $parts[0] < self::MINIMUM_SECONDS) {
            throw SpamSuspected::tooFast();
        }
    }

    private function signature(string $issued): string
    {
        return substr(hash_hmac('sha256', 'registration.form|'.$issued, $this->secret), 0, 32);
    }
}
