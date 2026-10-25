<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot\Webhook;

use Eventor\Payments\Application\Webhook\PairrotWebhookSignature;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * `%env(pairrot_keys:PAIRROT_WEBHOOK_SECRETS)%`: a comma-separated list of `pwhsec_...` secrets, each optionally
 * prefixed with the key id (`keyid:pwhsec_...`), becomes `array<string, string>` of key id => 32 raw bytes
 * (chapter 09). Several entries exist during a rotation (Pairrot signs with both for 24 h). A secret without id is
 * keyed `*0`, `*1`, ... and accepts whatever key id the signature header names. An empty variable is an empty list,
 * which makes the webhook endpoint refuse every request.
 */
final class PairrotKeysEnvVarProcessor implements EnvVarProcessorInterface
{
    /**
     * @return array<string, string>
     */
    public function getEnv(string $prefix, string $name, \Closure $getEnv): array
    {
        $value = $getEnv($name);
        if (!\is_string($value)) {
            return [];
        }

        $keys = [];
        $anonymous = 0;
        foreach (explode(',', $value) as $entry) {
            $entry = trim($entry);
            if ('' === $entry) {
                continue;
            }
            [$id, $secret] = str_contains($entry, ':') ? explode(':', $entry, 2) : ['*'.$anonymous++, $entry];

            try {
                $keys[$id] = PairrotWebhookSignature::decodeSecret(trim($secret));
            } catch (\InvalidArgumentException $e) {
                throw new RuntimeException(\sprintf('Invalid Pairrot webhook secret in "%s" (entry %d): %s', $name, \count($keys) + 1, $e->getMessage()), 0, $e);
            }
        }

        return $keys;
    }

    public static function getProvidedTypes(): array
    {
        return ['pairrot_keys' => 'array'];
    }
}
