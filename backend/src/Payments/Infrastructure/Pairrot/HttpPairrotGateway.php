<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot;

use Eventor\Payments\Application\Port\AllocationRequest;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayRejected;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayUnavailable;
use Eventor\Payments\Application\Port\Exception\PaymentRequestConflict;
use Eventor\Payments\Application\Port\Exception\PaymentRequestHasAllocations;
use Eventor\Payments\Application\Port\IssuedPaymentRequest;
use Eventor\Payments\Application\Port\PairrotBankConnection;
use Eventor\Payments\Application\Port\PairrotRequestSnapshot;
use Eventor\Payments\Application\Port\PairrotSubtenant;
use Eventor\Payments\Application\Port\PaymentGateway;
use Eventor\Payments\Application\Port\PaymentRequestSpec;
use Eventor\Payments\Application\Port\WebhookEventPage;
use Eventor\Payments\Contract\Exception\BankAccountUnavailable;
use Eventor\Payments\Contract\ReceptionState;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Pairrot adapter (chapter 07): REST API v1 with the management key of Eventor's root tenant, acting on
 * `/v1/subtenants/{id}/...`. The scoped client `pairrot.client` carries the base URL, the Bearer key and the timeouts
 * (2 s to connect, 10 s overall). Resilience (retry, circuit breaker, logging) is added by decorators, not here.
 */
#[AsAlias(PaymentGateway::class)]
final readonly class HttpPairrotGateway implements PaymentGateway
{
    public function __construct(
        #[Autowire(service: 'pairrot.client')]
        private HttpClientInterface $http,
    ) {}

    public function createRequest(PaymentRequestSpec $spec): IssuedPaymentRequest
    {
        $path = \sprintf('/v1/subtenants/%s/payment-requests', $spec->subtenantId);
        $result = $this->send('POST', $path, ['json' => [
            'bank_connection_id' => $spec->bankConnectionId,
            'external_id' => $spec->externalId,
            'amount' => $spec->amount->minor,
            'currency' => $spec->amount->currency->value,
            'message' => $spec->message,
            'description' => $spec->description,
            'due_at' => $spec->dueAt->format(\DATE_ATOM),
        ]]);

        if (409 === $result->status) {
            throw new PaymentRequestConflict($result->errorCode());
        }
        $this->expect($result, [200, 201]);

        $data = $result->data;
        $fallback = null;
        if (!\is_array($data['payment_instructions'] ?? null)) {
            // A replay of a request that is no longer payable carries no instructions; the frozen payload may still be readable.
            $id = PairrotMapper::string($data, 'id');
            $instructions = $this->send('GET', \sprintf('/v1/subtenants/%s/payment-requests/%s/payment-instructions', $spec->subtenantId, $id));
            $fallback = 200 === $instructions->status ? PairrotMapper::nullableString($instructions->data, 'payload') : null;
        }

        return PairrotMapper::issued($data, $fallback);
    }

    public function cancelRequest(string $subtenantId, string $pairrotRequestId, string $reason): void
    {
        $path = \sprintf('/v1/subtenants/%s/payment-requests/%s', $subtenantId, $pairrotRequestId);

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $current = $this->send('GET', $path);
            $this->expect($current, [200]);
            if ('cancelled' === ($current->data['status'] ?? null)) {
                return;
            }
            $version = $current->data['version'] ?? null;
            \assert(\is_int($version));

            $result = $this->send('POST', $path.'/cancel', ['json' => ['reason' => mb_substr($reason, 0, 200)], 'headers' => ['If-Match' => \sprintf('"%d"', $version)]]);
            if (412 === $result->status) {
                continue; // somebody changed the request between our read and write: read again
            }
            if (409 === $result->status && 'payment_request_has_allocations' === $result->errorCode()) {
                throw new PaymentRequestHasAllocations();
            }
            $this->expect($result, [200]);

            return;
        }

        throw new PaymentGatewayUnavailable('The payment request keeps changing; cancelling it was not possible.', 'pairrot_version_conflict');
    }

    public function getRequest(string $subtenantId, string $pairrotRequestId): PairrotRequestSnapshot
    {
        $result = $this->send('GET', \sprintf('/v1/subtenants/%s/payment-requests/%s', $subtenantId, $pairrotRequestId));
        $this->expect($result, [200]);

        return PairrotMapper::snapshot($result->data);
    }

    public function listWebhookEvents(\DateTimeImmutable $from, ?string $cursor = null, int $limit = 100): WebhookEventPage
    {
        $query = ['from' => $from->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), 'limit' => $limit];
        if (null !== $cursor) {
            $query['cursor'] = $cursor;
        }
        $result = $this->send('GET', '/v1/management/webhook-events', ['query' => $query]);
        $this->expect($result, [200]);

        $events = [];
        $items = $result->data['items'] ?? [];
        foreach (\is_array($items) ? $items : [] as $item) {
            if (\is_array($item) && \is_array($item['body'] ?? null)) {
                /** @var array<string, mixed> $body */
                $body = $item['body'];
                $events[] = $body;
            }
        }

        return new WebhookEventPage($events, PairrotMapper::nullableString($result->data, 'next_cursor'));
    }

    public function allocate(AllocationRequest $request): void
    {
        $base = \sprintf('/v1/subtenants/%s', $request->subtenantId);
        $transaction = $this->send('GET', $base.'/transactions/'.$request->pairrotTransactionId);
        $this->expect($transaction, [200]);
        $paymentRequest = $this->send('GET', $base.'/payment-requests/'.$request->pairrotRequestId);
        $this->expect($paymentRequest, [200]);

        $result = $this->send('POST', $base.'/transactions/'.$request->pairrotTransactionId.'/allocations', [
            'headers' => ['Idempotency-Key' => $request->idempotencyKey],
            'json' => [
                'payment_request_id' => $request->pairrotRequestId,
                'expected_transaction_version' => $transaction->data['version'] ?? null,
                'expected_request_version' => $paymentRequest->data['version'] ?? null,
                'reason' => mb_substr($request->reason, 0, 500),
            ],
        ]);
        if (412 === $result->status || 428 === $result->status) {
            throw new PaymentGatewayUnavailable('The transaction or request changed meanwhile; try again.', 'pairrot_stale_version');
        }
        $this->expect($result, [200, 201]);
    }

    public function createSubtenant(string $externalId, string $name, string $contactEmail): PairrotSubtenant
    {
        $result = $this->send('POST', '/v1/subtenants', ['json' => ['external_id' => $externalId, 'name' => $name, 'contact_email' => $contactEmail]]);
        if (409 === $result->status) {
            return $this->findSubtenant($externalId) ?? throw new PaymentGatewayRejected(409, $result->errorCode());
        }
        $this->expect($result, [200, 201]);
        $tenant = $result->data['tenant'] ?? null;
        \assert(\is_array($tenant));
        /** @var array<string, mixed> $tenant */

        return PairrotMapper::subtenant($tenant);
    }

    public function findSubtenant(string $externalId): ?PairrotSubtenant
    {
        $result = $this->send('GET', '/v1/subtenants', ['query' => ['external_id' => $externalId, 'limit' => 50]]);
        $this->expect($result, [200]);
        foreach (\is_array($result->data['items'] ?? null) ? $result->data['items'] : [] as $item) {
            if (\is_array($item) && ($item['external_id'] ?? null) === $externalId) {
                /** @var array<string, mixed> $item */
                return PairrotMapper::subtenant($item);
            }
        }

        return null;
    }

    public function connectBankAccount(string $subtenantId, string $accountNumberOrIban, string $label): PairrotBankConnection
    {
        $account = preg_replace('/\s+/', '', $accountNumberOrIban) ?? $accountNumberOrIban;
        $field = str_contains($account, '/') ? 'account_number' : 'iban';
        $path = \sprintf('/v1/subtenants/%s/bank-connections', $subtenantId);

        $result = $this->send('POST', $path, ['json' => [$field => $account, 'currency' => 'CZK', 'label' => mb_substr($label, 0, 100)]]);
        if (409 === $result->status) {
            if ('account_unavailable' === $result->errorCode()) {
                throw BankAccountUnavailable::alreadyConnected();
            }
            // bank_connection_exists: the wizard is repeated, hand back the connection of this account
            $list = $this->send('GET', $path);
            $this->expect($list, [200]);
            foreach (\is_array($list->data['items'] ?? null) ? $list->data['items'] : [] as $item) {
                if (\is_array($item) && 'active' === ($item['status'] ?? null) && (($item['iban'] ?? null) === $account || ($item['account_number'] ?? null) === $account)) {
                    /** @var array<string, mixed> $item */
                    return PairrotMapper::bankConnection($item);
                }
            }
            throw new PaymentGatewayRejected(409, $result->errorCode());
        }
        $this->expect($result, [200, 201]);

        return PairrotMapper::bankConnection($result->data);
    }

    public function createInboundAddress(string $subtenantId, string $bankConnectionId): string
    {
        $result = $this->send('POST', \sprintf('/v1/subtenants/%s/bank-connections/%s/inbound-address', $subtenantId, $bankConnectionId));
        $this->expect($result, [200, 201]);

        return PairrotMapper::string($result->data, 'inbound_address');
    }

    public function receptionState(string $subtenantId, string $bankConnectionId): ReceptionState
    {
        $result = $this->send('GET', \sprintf('/v1/subtenants/%s/bank-connections/%s/reception', $subtenantId, $bankConnectionId));
        $this->expect($result, [200]);

        return PairrotMapper::reception($result->data);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function send(string $method, string $path, array $options = []): PairrotResponse
    {
        try {
            $response = $this->http->request($method, $path, $options + ['headers' => []]);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (HttpClientException $e) {
            throw new PaymentGatewayUnavailable('Pairrot could not be reached.', 'pairrot_transport_error', $e);
        }

        if ($status >= 500 || \in_array($status, [408, 429], true)) {
            throw new PaymentGatewayUnavailable(\sprintf('Pairrot answered %d.', $status), 'pairrot_http_'.$status);
        }

        $data = [];
        if ('' !== $content) {
            try {
                $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new PaymentGatewayUnavailable('Pairrot sent an unreadable answer.', 'pairrot_bad_payload', $e);
            }
            /** @var array<string, mixed> $data */
            $data = \is_array($decoded) ? $decoded : [];
        }

        $headers = $response->getHeaders(false);

        return new PairrotResponse($status, $data, $headers);
    }

    /**
     * @param list<int> $accepted
     */
    private function expect(PairrotResponse $response, array $accepted): void
    {
        if (!\in_array($response->status, $accepted, true)) {
            throw new PaymentGatewayRejected($response->status, $response->errorCode());
        }
    }
}
