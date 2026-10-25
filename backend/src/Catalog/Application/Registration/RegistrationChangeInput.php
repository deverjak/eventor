<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Registration;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Optional body of the registration window endpoints (EVT-04).
 */
final readonly class RegistrationChangeInput
{
    public function __construct(
        #[Assert\Length(max: 500)]
        public ?string $reason = null,
        public bool $cascade = false,
    ) {}
}
