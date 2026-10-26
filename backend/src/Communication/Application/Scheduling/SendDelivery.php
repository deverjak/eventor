<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Scheduling;

/**
 * Queue message on the `async_mail` transport (routing in the module's services.php): attempt one delivery. It carries
 * the tenant explicitly so that a message created by the sweeper (outside any tenant) runs in the right one.
 */
final readonly class SendDelivery
{
    public function __construct(
        public string $deliveryId,
        public string $organizerId,
    ) {}
}
