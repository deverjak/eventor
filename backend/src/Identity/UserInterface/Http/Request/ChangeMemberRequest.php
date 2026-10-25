<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeMemberRequest
{
    /**
     * @param list<string> $eventScope event ids the member is restricted to; empty = all events (EVENT_MANAGER and CHECK_IN only)
     */
    public function __construct(
        #[Assert\Choice(choices: ['OWNER', 'EVENT_MANAGER', 'FINANCE', 'CHECK_IN'])]
        public string $role = '',
        #[Assert\All([new Assert\Uuid()])]
        public array $eventScope = [],
    ) {}
}
