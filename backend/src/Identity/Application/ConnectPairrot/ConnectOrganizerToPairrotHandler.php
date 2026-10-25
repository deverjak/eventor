<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\ConnectPairrot;

use Eventor\Identity\Contract\Exception\OrganizerNotFound;
use Eventor\Identity\Domain\Organizer\ContactEmailRequired;
use Eventor\Identity\Domain\Organizer\OrganizerRepository;
use Eventor\Payments\Contract\Exception\PairrotUnavailable;
use Eventor\Payments\Contract\PairrotOnboarding;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The onboarding wizard (chapter 07): step 1 activates the subtenant, step 2 connects the bank account; the result
 * lands on the organizer. The facade is optional at wiring time so Identity boots before Payments ships its adapter;
 * without it the wizard answers 503 `pairrot_unavailable`.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ConnectOrganizerToPairrotHandler
{
    public function __construct(
        private OrganizerRepository $organizers,
        private ?PairrotOnboarding $onboarding = null,
    ) {}

    /**
     * @return array{subtenantId: string, bankConnectionId: string, maskedIban: string, inboundAddress: string|null}
     */
    public function __invoke(ConnectOrganizerToPairrot $command): array
    {
        $organizer = $this->organizers->findById($command->organizerId) ?? throw OrganizerNotFound::forId($command->organizerId);
        $contactEmail = $organizer->contactEmail() ?? throw ContactEmailRequired::create();
        $onboarding = $this->onboarding ?? throw PairrotUnavailable::because('The Pairrot integration is not available.');

        $subtenantId = $onboarding->activateSubtenant($organizer->id(), $organizer->name(), $contactEmail);
        $connection = $onboarding->connectBankAccount($organizer->id(), $subtenantId, $command->accountNumberOrIban, $command->label);

        $organizer->connectPairrot($subtenantId, $connection->bankConnectionId, $connection->maskedIban);

        return [
            'subtenantId' => $subtenantId,
            'bankConnectionId' => $connection->bankConnectionId,
            'maskedIban' => $connection->maskedIban,
            'inboundAddress' => $connection->inboundAddress,
        ];
    }
}
