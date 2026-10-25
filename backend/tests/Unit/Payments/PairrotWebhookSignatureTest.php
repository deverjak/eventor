<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Payments;

use Eventor\Payments\Application\Webhook\PairrotWebhookSignature;
use Eventor\Payments\Infrastructure\Pairrot\Webhook\PairrotKeysEnvVarProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * ADR 004, "Verification": signature checked against the test vector of Pairrot's `docs/webhooks-receiver.md`, a key
 * rotation with two signatures and a skewed timestamp.
 */
final class PairrotWebhookSignatureTest extends TestCase
{
    // The published vector (docs/webhooks-receiver.md, "Verifying the signature").
    private const string SECRET = 'pwhsec_AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8';
    private const string DELIVERY_ID = '01900000-0000-7000-8000-000000000001';
    private const string TIMESTAMP = '1791374400';
    private const string BODY = '{"id":"01900000-0000-7000-8000-0000000000aa","type":"payment_request.updated"}';
    private const string SIGNATURE = 'IO/pJAG2O51hyi8WurN0lY2gbAnoj9z/aI2jU9YuTAQ=';

    public function test_the_secret_decodes_to_the_32_bytes_of_the_vector(): void
    {
        self::assertSame(implode('', array_map(chr(...), range(0, 31))), PairrotWebhookSignature::decodeSecret(self::SECRET));
    }

    public function test_the_hmac_matches_the_published_vector(): void
    {
        $key = PairrotWebhookSignature::decodeSecret(self::SECRET);

        self::assertSame(self::SIGNATURE, PairrotWebhookSignature::compute(self::DELIVERY_ID, self::TIMESTAMP, self::BODY, $key));
    }

    public function test_a_valid_header_is_accepted_within_the_window(): void
    {
        $keys = ['k1' => PairrotWebhookSignature::decodeSecret(self::SECRET)];

        self::assertTrue(PairrotWebhookSignature::verify($keys, self::DELIVERY_ID, self::TIMESTAMP, 'v1,k1,'.self::SIGNATURE, self::BODY, $this->at(0)));
        self::assertTrue(PairrotWebhookSignature::verify($keys, self::DELIVERY_ID, self::TIMESTAMP, 'v1,k1,'.self::SIGNATURE, self::BODY, $this->at(299)));
        self::assertTrue(PairrotWebhookSignature::verify($keys, self::DELIVERY_ID, self::TIMESTAMP, 'v1,k1,'.self::SIGNATURE, self::BODY, $this->at(-299)));
    }

    public function test_a_skewed_timestamp_is_refused_in_both_directions(): void
    {
        $keys = ['k1' => PairrotWebhookSignature::decodeSecret(self::SECRET)];

        self::assertFalse(PairrotWebhookSignature::verify($keys, self::DELIVERY_ID, self::TIMESTAMP, 'v1,k1,'.self::SIGNATURE, self::BODY, $this->at(301)));
        self::assertFalse(PairrotWebhookSignature::verify($keys, self::DELIVERY_ID, self::TIMESTAMP, 'v1,k1,'.self::SIGNATURE, self::BODY, $this->at(-301)));
    }

    public function test_a_changed_body_id_or_timestamp_is_refused(): void
    {
        $keys = ['k1' => PairrotWebhookSignature::decodeSecret(self::SECRET)];
        $header = 'v1,k1,'.self::SIGNATURE;

        self::assertFalse(PairrotWebhookSignature::verify($keys, self::DELIVERY_ID, self::TIMESTAMP, $header, self::BODY.' ', $this->at(0)));
        self::assertFalse(PairrotWebhookSignature::verify($keys, 'other-id', self::TIMESTAMP, $header, self::BODY, $this->at(0)));
        self::assertFalse(PairrotWebhookSignature::verify($keys, self::DELIVERY_ID, '1791374401', $header, self::BODY, $this->at(0)));
    }

    public function test_during_a_rotation_either_of_two_signatures_may_match(): void
    {
        $old = PairrotWebhookSignature::decodeSecret(self::SECRET);
        $new = str_repeat("\x07", 32);
        $header = PairrotWebhookSignature::header(self::DELIVERY_ID, self::TIMESTAMP, self::BODY, 'new', $new).' '.PairrotWebhookSignature::header(self::DELIVERY_ID, self::TIMESTAMP, self::BODY, 'old', $old);

        // We still only know the old secret: the second entry matches.
        self::assertTrue(PairrotWebhookSignature::verify(['old' => $old], self::DELIVERY_ID, self::TIMESTAMP, $header, self::BODY, $this->at(0)));
        // We already know the new one only: the first entry matches.
        self::assertTrue(PairrotWebhookSignature::verify(['new' => $new], self::DELIVERY_ID, self::TIMESTAMP, $header, self::BODY, $this->at(0)));
        // Both known: still fine.
        self::assertTrue(PairrotWebhookSignature::verify(['old' => $old, 'new' => $new], self::DELIVERY_ID, self::TIMESTAMP, $header, self::BODY, $this->at(0)));
        // A stranger's key matches neither.
        self::assertFalse(PairrotWebhookSignature::verify(['x' => str_repeat("\x01", 32)], self::DELIVERY_ID, self::TIMESTAMP, $header, self::BODY, $this->at(0)));
    }

    public function test_a_secret_without_id_accepts_any_key_id_of_the_header(): void
    {
        $key = PairrotWebhookSignature::decodeSecret(self::SECRET);

        self::assertTrue(PairrotWebhookSignature::verify(['*0' => $key], self::DELIVERY_ID, self::TIMESTAMP, 'v1,whatever,'.self::SIGNATURE, self::BODY, $this->at(0)));
        self::assertFalse(PairrotWebhookSignature::verify(['k1' => $key], self::DELIVERY_ID, self::TIMESTAMP, 'v1,other,'.self::SIGNATURE, self::BODY, $this->at(0)), 'a named key only matches its own id');
    }

    public function test_garbage_is_refused_without_an_exception(): void
    {
        $keys = ['k1' => PairrotWebhookSignature::decodeSecret(self::SECRET)];

        foreach (['', 'v1', 'v1,k1', 'v2,k1,'.self::SIGNATURE, 'nonsense nonsense', 'v1,k1,!!!'] as $header) {
            self::assertFalse(PairrotWebhookSignature::verify($keys, self::DELIVERY_ID, self::TIMESTAMP, $header, self::BODY, $this->at(0)), $header);
        }
        self::assertFalse(PairrotWebhookSignature::verify($keys, '', self::TIMESTAMP, 'v1,k1,'.self::SIGNATURE, self::BODY, $this->at(0)));
        self::assertFalse(PairrotWebhookSignature::verify($keys, self::DELIVERY_ID, 'abc', 'v1,k1,'.self::SIGNATURE, self::BODY, $this->at(0)));
        self::assertFalse(PairrotWebhookSignature::verify([], self::DELIVERY_ID, self::TIMESTAMP, 'v1,k1,'.self::SIGNATURE, self::BODY, $this->at(0)), 'no configured secret: refuse everything');
    }

    public function test_the_env_processor_turns_the_secrets_into_keys(): void
    {
        $processor = new PairrotKeysEnvVarProcessor();
        $value = 'new:'.self::SECRET.', '.self::SECRET.' ,';

        $keys = $processor->getEnv('pairrot_keys', 'X', static fn(string $name): string => $value);

        self::assertSame(['new', '*0'], array_keys($keys));
        self::assertSame(PairrotWebhookSignature::decodeSecret(self::SECRET), $keys['new']);
        self::assertSame([], $processor->getEnv('pairrot_keys', 'X', static fn(string $name): string => ''));
        self::assertSame(['pairrot_keys' => 'array'], PairrotKeysEnvVarProcessor::getProvidedTypes());
    }

    public function test_the_env_processor_refuses_a_malformed_secret(): void
    {
        $this->expectException(RuntimeException::class);

        (new PairrotKeysEnvVarProcessor())->getEnv('pairrot_keys', 'X', static fn(string $name): string => 'pwhsec_tooshort');
    }

    private function at(int $offsetSeconds): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@'.(int) self::TIMESTAMP))->modify(\sprintf('%+d seconds', $offsetSeconds));
    }
}
