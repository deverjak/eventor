<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Serializer;

use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Infrastructure\Formatting\MoneyFormatter;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * API shape of money: `{ "minor": 30000, "currency": "CZK", "formatted": "300 Kč" }` (chapter 08: integer minor
 * units, never a float). `formatted` is a convenience for display; it is ignored when money is sent to the API.
 */
final readonly class MoneyNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(private MoneyFormatter $formatter) {}

    /**
     * @return array{minor: int, currency: string, formatted: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof Money) {
            throw new InvalidArgumentException('Expected Money.');
        }

        return [
            'minor' => $data->minor,
            'currency' => $data->currency->value,
            'formatted' => $this->formatter->format($data),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Money;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $path = $context['deserialization_path'] ?? null;
        $path = \is_string($path) ? $path : null;

        if (!\is_array($data) || !isset($data['minor'], $data['currency']) || !\is_int($data['minor']) || !\is_string($data['currency'])) {
            throw NotNormalizableValueException::createForUnexpectedDataType('Money must be an object {"minor": int, "currency": "CZK"}.', $data, ['array'], $path);
        }

        $currency = Currency::tryFrom($data['currency']);
        if (null === $currency) {
            throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('Unsupported currency "%s".', $data['currency']), $data, ['string'], $path);
        }

        return new Money($data['minor'], $currency);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Money::class === $type;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [Money::class => true];
    }
}
