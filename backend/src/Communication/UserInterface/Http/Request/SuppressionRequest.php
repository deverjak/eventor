<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http\Request;

use Eventor\Communication\Contract\SuppressionScope;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SuppressionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        public SuppressionScope $scope = SuppressionScope::Marketing,
    ) {}
}
