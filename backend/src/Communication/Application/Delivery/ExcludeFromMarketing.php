<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Delivery;

/**
 * A consent was withdrawn (S16): marketing messages still waiting for this order are skipped at once.
 */
final readonly class ExcludeFromMarketing
{
    public function __construct(public string $orderId) {}
}
