<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * Payment and cancellation rules of one event (EVT-10): the enabled payment methods, the due date of a transfer, the
 * hold time of a reservation, the behaviour of a late payment and the self-service cancellation window. A free order
 * always uses `PaymentMethod::Free`, which is never listed here.
 */
final readonly class PaymentRules
{
    /**
     * @param list<PaymentMethod> $methods
     */
    public function __construct(
        public array $methods,
        public int $dueDays,
        public int $holdTtlHours,
        public bool $autoRestoreOnLatePayment,
        public bool $selfServiceCancellationAllowed,
        public ?\DateTimeImmutable $selfServiceCancellationDeadline,
        public Currency $currency = Currency::CZK,
    ) {}

    public static function defaults(int $dueDays = 7, int $holdTtlHours = 48, bool $autoRestoreOnLatePayment = false): self
    {
        return new self([], $dueDays, $holdTtlHours, $autoRestoreOnLatePayment, true, null);
    }

    public function allows(PaymentMethod $method): bool
    {
        return \in_array($method, $this->methods, true);
    }

    /**
     * @return array{methods: list<string>, dueDays: int, holdTtlHours: int, autoRestoreOnLatePayment: bool, selfServiceCancellationAllowed: bool, selfServiceCancellationDeadline: string|null, currency: string}
     */
    public function toArray(): array
    {
        return [
            'methods' => array_map(static fn(PaymentMethod $method): string => $method->value, $this->methods),
            'dueDays' => $this->dueDays,
            'holdTtlHours' => $this->holdTtlHours,
            'autoRestoreOnLatePayment' => $this->autoRestoreOnLatePayment,
            'selfServiceCancellationAllowed' => $this->selfServiceCancellationAllowed,
            'selfServiceCancellationDeadline' => $this->selfServiceCancellationDeadline?->format(\DATE_ATOM),
            'currency' => $this->currency->value,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $methods = [];
        foreach (\is_array($data['methods'] ?? null) ? $data['methods'] : [] as $value) {
            $method = \is_string($value) ? PaymentMethod::tryFrom($value) : null;
            if (null !== $method && PaymentMethod::Free !== $method && !\in_array($method, $methods, true)) {
                $methods[] = $method;
            }
        }

        $deadline = $data['selfServiceCancellationDeadline'] ?? null;

        return new self(
            $methods,
            \is_int($data['dueDays'] ?? null) ? $data['dueDays'] : 7,
            \is_int($data['holdTtlHours'] ?? null) ? $data['holdTtlHours'] : 48,
            true === ($data['autoRestoreOnLatePayment'] ?? false),
            false !== ($data['selfServiceCancellationAllowed'] ?? true),
            \is_string($deadline) ? new \DateTimeImmutable($deadline) : null,
            \is_string($data['currency'] ?? null) ? (Currency::tryFrom($data['currency']) ?? Currency::CZK) : Currency::CZK,
        );
    }
}
