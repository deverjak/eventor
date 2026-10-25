<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Request;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\PaymentRequestId;

interface PaymentRequestRepository
{
    public function add(PaymentRequest $request): void;

    public function find(PaymentRequestId $id): ?PaymentRequest;

    /**
     * The request with a row lock; only valid inside a transaction.
     */
    public function lock(PaymentRequestId $id): ?PaymentRequest;

    /**
     * The request Pairrot's `payment_request_id` (or, before we learnt it, `external_id`) refers to, with a row lock.
     */
    public function lockByPairrotReference(string $pairrotId, ?string $externalId): ?PaymentRequest;

    public function findByOrderAndAttempt(OrderId $order, int $attempt): ?PaymentRequest;

    /**
     * All requests of an order, oldest attempt first.
     *
     * @return list<PaymentRequest>
     */
    public function forOrder(OrderId $order): array;

    public function highestAttempt(OrderId $order): int;
}
