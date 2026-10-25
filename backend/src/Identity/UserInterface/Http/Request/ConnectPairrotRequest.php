<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConnectPairrotRequest
{
    public function __construct(
        #[\SensitiveParameter]
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $accountNumberOrIban = '',
        #[Assert\Length(max: 100)]
        public string $label = 'Eventor',
    ) {}
}
