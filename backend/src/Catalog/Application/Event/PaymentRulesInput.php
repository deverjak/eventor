<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Event;

use Eventor\Catalog\Domain\Structure\PaymentRules;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of `PUT /events/{id}/payment-rules` (EVT-10).
 */
final readonly class PaymentRulesInput
{
    /**
     * @param list<string> $methods `bank_transfer` and / or `cash`
     */
    public function __construct(
        #[Assert\All(constraints: [new Assert\Choice(choices: ['bank_transfer', 'cash'])])]
        public array $methods = [],
        #[Assert\Range(min: 1, max: 90)]
        public int $dueDays = 7,
        #[Assert\Range(min: 1, max: 720)]
        public int $holdTtlHours = 48,
        public bool $autoRestoreOnLatePayment = false,
        public bool $selfServiceCancellationAllowed = true,
        public ?\DateTimeImmutable $selfServiceCancellationDeadline = null,
    ) {}

    public function toRules(): PaymentRules
    {
        $methods = [];
        foreach ($this->methods as $value) {
            $method = PaymentMethod::tryFrom($value);
            if (null !== $method && !\in_array($method, $methods, true)) {
                $methods[] = $method;
            }
        }

        return new PaymentRules($methods, $this->dueDays, $this->holdTtlHours, $this->autoRestoreOnLatePayment, $this->selfServiceCancellationAllowed, $this->selfServiceCancellationDeadline, Currency::CZK);
    }
}
