<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\SelfService;

use Eventor\Shared\Domain\Id\OrderId;

/**
 * Self-service tokens of the active tenant.
 */
interface SelfServiceTokenRepository
{
    /**
     * The newest usable token of the order, if any.
     */
    public function usableOfOrder(OrderId $order, \DateTimeImmutable $now): ?SelfServiceToken;

    public function add(SelfServiceToken $token): void;
}
