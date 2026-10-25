<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Payments;

use Eventor\Payments\Application\Webhook\PairrotWebhookSignature;
use Eventor\Payments\Infrastructure\Pairrot\Webhook\LocalWebhookSecret;
use Eventor\Tests\Api\Support\ApiTestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * POST /api/webhooks/pairrot over real HTTP (API-02): the signed round trip, deduplication (S03), and the 401 cases.
 * The stack outside production accepts the published local test secret (no secret is shared out of band).
 */
final class PairrotWebhookApiTest extends ApiTestCase
{
    public function test_a_signed_event_is_accepted_and_a_duplicate_is_acknowledged_without_a_second_effect(): void
    {
        $event = $this->event('webhook.test');

        $first = $this->post($event);
        self::assertSame(200, $first->getStatusCode(), $first->getContent(false));
        self::assertSame('accepted', $first->toArray()['status']);

        $second = $this->post($event);
        self::assertSame(200, $second->getStatusCode());
        self::assertSame('duplicate', $second->toArray()['status'], 'the same event id twice ends at the unique key');
    }

    public function test_the_same_payment_event_delivered_three_times_is_one_message(): void
    {
        $event = $this->event('payment_request.updated', [
            'payment_request_id' => bin2hex(random_bytes(8)), 'external_id' => 'order_unknown_1', 'sequence' => 1, 'cause' => 'allocation_created',
            'current' => ['status' => 'paid', 'paid_amount' => 100],
        ]);

        $answers = [];
        for ($i = 0; $i < 3; ++$i) {
            $response = $this->post($event);
            self::assertSame(200, $response->getStatusCode());
            $answers[] = $response->toArray()['status'];
        }

        self::assertSame(['accepted', 'duplicate', 'duplicate'], $answers);
    }

    public function test_a_wrong_signature_is_401(): void
    {
        $event = $this->event('webhook.test');
        $event['headers']['Pairrot-Webhook-Signature'] = 'v1,local,'.base64_encode(random_bytes(32));

        $response = $this->post($event);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('invalid_signature', $response->toArray(false)['code'] ?? null);
    }

    public function test_a_stale_timestamp_is_401(): void
    {
        foreach ([-600, 600] as $offset) {
            self::assertSame(401, $this->post($this->event('webhook.test', ['synthetic' => true], time() + $offset))->getStatusCode(), "offset $offset");
        }
    }

    public function test_an_unsigned_request_is_401(): void
    {
        $response = HttpClient::create()->request('POST', rtrim(static::baseUrl(), '/').'/api/webhooks/pairrot', ['body' => '{"id":"x","type":"webhook.test"}', 'headers' => ['Content-Type' => 'application/json']]);

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_a_signed_body_that_is_not_an_event_is_400_with_a_problem(): void
    {
        $response = $this->post($this->sign('{"hello":"world"}'));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalid_webhook', $response->toArray(false)['code'] ?? null);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{body: string, headers: array<string, string>}
     */
    private function event(string $type, array $data = ['synthetic' => true], ?int $at = null): array
    {
        return $this->sign(json_encode([
            'id' => \sprintf('%08x-0000-7000-8000-%012x', random_int(0, 0xFFFFFFFF), random_int(0, 0xFFFFFFFFFFFF)),
            'type' => $type,
            'schema_version' => 1,
            'created_at' => gmdate('Y-m-d\TH:i:s.u\Z'),
            'environment' => 'live',
            'tenant_id' => 'api-test',
            'data' => $data,
        ], \JSON_THROW_ON_ERROR), $at);
    }

    /**
     * @return array{body: string, headers: array<string, string>}
     */
    private function sign(string $body, ?int $at = null): array
    {
        $timestamp = (string) ($at ?? time());
        $id = bin2hex(random_bytes(8));

        return [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Pairrot-Webhook-Id' => $id,
                'Pairrot-Webhook-Timestamp' => $timestamp,
                'Pairrot-Webhook-Signature' => PairrotWebhookSignature::header($id, $timestamp, $body, LocalWebhookSecret::KEY_ID, PairrotWebhookSignature::decodeSecret(LocalWebhookSecret::SECRET)),
            ],
        ];
    }

    /**
     * @param array{body: string, headers: array<string, string>} $request
     */
    private function post(array $request): ResponseInterface
    {
        return HttpClient::create()->request('POST', rtrim(static::baseUrl(), '/').'/api/webhooks/pairrot', ['body' => $request['body'], 'headers' => $request['headers'], 'timeout' => 15]);
    }
}
