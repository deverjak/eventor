<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Suppression;

final readonly class LiftSuppression
{
    public function __construct(public string $suppressionId) {}
}
