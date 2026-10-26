<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Http\Request;

use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Registration\Application\PlaceOrder\DecisionInput;
use Eventor\Registration\Application\PlaceOrder\OrderItemInput;
use Eventor\Registration\Application\PlaceOrder\PersonInput;
use Eventor\Registration\Application\PlaceOrder\PlaceOrder;
use Eventor\Registration\Contract\Exception\InvalidOrderRequest;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Id\WaveId;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * The body of `POST .../orders` (REG-01): items, persons with their answers, the order contact answers, the legal
 * decisions, the payment method, plus the antispam fields (`website` honeypot, `formToken`) which are not part of the order.
 *
 *     { "items": [{"personRef": "p1", "activityId": "...", "variantId": "...", "itemRef": "p1"}],
 *       "persons": [{"personRef": "p1", "answers": {"first_name": "Jana"}}],
 *       "orderAnswers": {"orderer_email": "jana@example.test"},
 *       "decisions": [{"requirementId": "...", "documentVersionId": "...", "decision": "granted", "personRef": "p1"}],
 *       "paymentMethod": "cash", "locale": "cs", "website": "", "formToken": "..." }
 */
final readonly class PlaceOrderPayload
{
    private function __construct(
        public PlaceOrder $command,
        public ?string $honeypot,
        public ?string $formToken,
    ) {}

    /**
     * @param array<array-key, mixed> $body
     */
    public static function parse(array $body, OrganizerId $organizer, EventId $event): self
    {
        $items = [];
        foreach (Payload::objects($body, 'items') as $i => $item) {
            $path = 'items.'.$i.'.';
            $waveId = Payload::uuid($item, 'waveId', $path, false);
            $items[] = new OrderItemInput(
                (string) Payload::string($item, 'personRef', $path),
                ActivityId::fromString((string) Payload::uuid($item, 'activityId', $path)),
                VariantId::fromString((string) Payload::uuid($item, 'variantId', $path)),
                null === $waveId ? null : WaveId::fromString($waveId),
                Payload::string($item, 'itemRef', $path, false),
            );
        }

        $persons = [];
        foreach (Payload::objects($body, 'persons') as $i => $person) {
            $persons[] = new PersonInput((string) Payload::string($person, 'personRef', 'persons.'.$i.'.'), Payload::object($person, 'answers', 'persons.'.$i.'.'));
        }

        $decisions = [];
        foreach (Payload::objects($body, 'decisions') as $i => $decision) {
            $path = 'decisions.'.$i.'.';
            $value = ConsentDecision::tryFrom((string) Payload::string($decision, 'decision', $path));
            if (null === $value || ConsentDecision::Requested === $value || ConsentDecision::Withdrawn === $value) {
                throw InvalidOrderRequest::at($path.'decision', 'invalid_decision', 'The decision is "granted" or "declined".');
            }
            $decisions[] = new DecisionInput(
                LegalRequirementId::fromString((string) Payload::uuid($decision, 'requirementId', $path)),
                DocumentVersionId::fromString((string) Payload::uuid($decision, 'documentVersionId', $path)),
                $value,
                Payload::string($decision, 'personRef', $path, false),
                Payload::string($decision, 'itemRef', $path, false),
            );
        }

        $method = null;
        $rawMethod = Payload::string($body, 'paymentMethod', '', false);
        if (null !== $rawMethod) {
            $method = PaymentMethod::tryFrom($rawMethod) ?? throw InvalidOrderRequest::at('paymentMethod', 'invalid_value', 'Unknown payment method.');
        }

        $locale = Payload::string($body, 'locale', '', false) ?? 'cs';
        if (1 !== preg_match('/^[a-z]{2}(-[A-Z]{2})?$/D', $locale)) {
            throw InvalidOrderRequest::at('locale', 'invalid_value', 'Unknown locale.');
        }

        $honeypot = $body['website'] ?? null;
        $token = $body['formToken'] ?? null;

        return new self(
            new PlaceOrder($organizer, $event, $items, $persons, Payload::object($body, 'orderAnswers'), $decisions, $method, $locale),
            \is_string($honeypot) ? $honeypot : null,
            \is_string($token) ? $token : null,
        );
    }

    /**
     * The canonical body of the idempotency hash: the order without the antispam fields (chapter 08).
     *
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    public static function canonical(array $body): array
    {
        unset($body['website'], $body['formToken']);

        return $body;
    }
}
