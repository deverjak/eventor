<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publish\Rule;

use Eventor\Catalog\Application\Publish\PublishCandidate;
use Eventor\Catalog\Application\Publish\PublishRule;

final readonly class PaidVariantNeedsPaymentMethod implements PublishRule
{
    public function check(PublishCandidate $candidate): array
    {
        if ([] !== $candidate->event->paymentRules()->methods) {
            return [];
        }
        foreach ($candidate->variants as $variant) {
            if ($variant->price()->isPositive()) {
                return [['path' => 'paymentRules.methods', 'message' => 'A paid variant needs at least one payment method.', 'code' => 'payment_method_required']];
            }
        }

        return [];
    }
}
