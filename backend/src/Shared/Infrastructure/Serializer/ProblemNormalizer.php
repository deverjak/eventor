<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Serializer;

use Eventor\Shared\UserInterface\Http\Problem\Problem;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Serialises a Problem as an RFC 9457 document. Empty optional members are omitted.
 */
final class ProblemNormalizer implements NormalizerInterface
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof Problem) {
            throw new InvalidArgumentException('Expected a Problem.');
        }

        $document = [
            'type' => $data->type(),
            'title' => $data->title,
            'status' => $data->status,
            'code' => $data->code,
        ];

        if (null !== $data->detail) {
            $document['detail'] = $data->detail;
        }
        if ([] !== $data->context) {
            $document['context'] = $data->context;
        }
        if ([] !== $data->nextSteps) {
            $document['nextSteps'] = $data->nextSteps;
        }
        if ([] !== $data->violations) {
            $document['violations'] = $data->violations;
        }
        if (null !== $data->correlationId) {
            $document['correlationId'] = $data->correlationId;
        }

        return $document;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Problem;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [Problem::class => true];
    }
}
