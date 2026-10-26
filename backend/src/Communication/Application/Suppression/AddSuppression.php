<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Suppression;

use Eventor\Communication\Contract\SuppressionScope;

/**
 * Puts an address on the suppression list (PRV-07, COM-09). Only its hash is stored.
 */
final readonly class AddSuppression
{
    public function __construct(
        public string $email,
        public SuppressionScope $scope,
        public string $reason = 'manual',
    ) {}
}
