<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Serializer;

use Eventor\Shared\Domain\Id\AbstractId;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Typed ids travel as plain UUID strings (JSON API, Messenger messages) and come back as the right id class.
 */
final class IdNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        \assert($data instanceof AbstractId);

        return $data->toString();
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AbstractId;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        \assert(is_subclass_of($type, AbstractId::class));

        if (!\is_string($data) || !$type::isValid($data)) {
            $path = $context['deserialization_path'] ?? null;

            throw NotNormalizableValueException::createForUnexpectedDataType('The value must be a UUID.', $data, ['string'], \is_string($path) ? $path : null, true);
        }

        return $type::fromString($data);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_subclass_of($type, AbstractId::class);
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [AbstractId::class => true];
    }
}
