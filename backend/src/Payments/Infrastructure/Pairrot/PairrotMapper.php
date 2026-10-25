<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot;

use Eventor\Payments\Application\Port\IssuedPaymentRequest;
use Eventor\Payments\Application\Port\PairrotBankConnection;
use Eventor\Payments\Application\Port\PairrotRequestSnapshot;
use Eventor\Payments\Application\Port\PairrotSubtenant;
use Eventor\Payments\Contract\ReceptionState;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Money;

/**
 * Wire format of Pairrot (snake_case, integer minor units) <-> the port's DTOs. The only class that knows the JSON
 * field names of responses; the adapter owns the paths and errors.
 */
final class PairrotMapper
{
    /**
     * @param array<string, mixed> $data the payment request (201, 200 replayed, GET)
     */
    public static function issued(array $data, ?string $spaydFallback = null): IssuedPaymentRequest
    {
        $instructions = \is_array($data['payment_instructions'] ?? null) ? $data['payment_instructions'] : [];
        $payload = $instructions['payload'] ?? $spaydFallback ?? '';

        return new IssuedPaymentRequest(
            self::string($data, 'id'),
            self::string($data, 'external_id'),
            self::string($data, 'variable_symbol'),
            self::nullableString($data, 'account_number'),
            self::nullableString($data, 'iban'),
            Money::of(self::int($data, 'amount'), Currency::from(self::string($data, 'currency'))),
            new \DateTimeImmutable(self::string($data, 'due_at')),
            \is_string($payload) ? $payload : '',
            true === ($data['replayed'] ?? false),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function snapshot(array $data): PairrotRequestSnapshot
    {
        return new PairrotRequestSnapshot(
            self::string($data, 'id'),
            self::string($data, 'external_id'),
            self::string($data, 'status'),
            self::int($data, 'paid_amount'),
            self::int($data, 'payment_change_sequence'),
        );
    }

    /**
     * @param array<string, mixed> $data the `tenant` object of a subtenant response, or a list item
     */
    public static function subtenant(array $data): PairrotSubtenant
    {
        return new PairrotSubtenant(self::string($data, 'id'), self::string($data, 'external_id'));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function bankConnection(array $data): PairrotBankConnection
    {
        return new PairrotBankConnection(self::string($data, 'id'), self::string($data, 'iban'), self::nullableString($data, 'inbound_address'));
    }

    /**
     * @param array<string, mixed> $data the `reception` response
     */
    public static function reception(array $data): ReceptionState
    {
        if (str_starts_with(self::nullableString($data, 'address_processing') ?? '', 'paused')) {
            return ReceptionState::Paused;
        }

        return match (self::nullableString($data, 'setup_status')) {
            'address_not_created' => ReceptionState::NotConfigured,
            'paused_foreign_mail' => ReceptionState::Paused,
            'bank_message_trusted' => ReceptionState::Active,
            default => ReceptionState::Waiting,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Pairrot response lacks the string field "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!\is_int($value)) {
            throw new \UnexpectedValueException(\sprintf('Pairrot response lacks the integer field "%s".', $key));
        }

        return $value;
    }
}
