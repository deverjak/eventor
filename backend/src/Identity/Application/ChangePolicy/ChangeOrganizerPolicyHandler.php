<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\ChangePolicy;

use Eventor\Identity\Domain\Organizer\OrganizerPolicyRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ChangeOrganizerPolicyHandler
{
    public function __construct(private OrganizerPolicyRepository $policies) {}

    public function __invoke(ChangeOrganizerPolicy $command): void
    {
        $this->policies->getOrCreate($command->organizerId)->change(
            $command->checkInMayRecordCash,
            $command->eventManagerMayResolvePayments,
            $command->autoRestoreLatePayment,
            $command->overpaymentHandling,
            $command->defaultDueDays,
            $command->defaultHoldTtlHours,
        );
    }
}
