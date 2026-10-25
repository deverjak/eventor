<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Event;

use Eventor\Shared\Domain\Id\EventId;

final readonly class ConfigurePaymentRules
{
    public function __construct(
        public EventId $eventId,
        public PaymentRulesInput $rules,
        public ?int $expectedVersion = null,
    ) {}
}
