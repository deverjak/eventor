<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\RaiseEscalation;

use Eventor\CheckIn\Application\Desk\CheckInAssessor;
use Eventor\CheckIn\Application\Desk\CheckInListGate;
use Eventor\CheckIn\Contract\Exception\NothingToEscalate;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\ParticipationId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RaiseEscalationHandler
{
    public function __construct(
        private CheckInListGate $gate,
        private CheckInAssessor $assessor,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{situation: string}
     */
    public function __invoke(RaiseEscalation $command): array
    {
        $list = $this->gate->open($command->eventId, $command->listId);
        $participation = ParticipationId::fromString($command->participationId);
        $verdict = $this->assessor->assess($this->tenant->require(), $list->activityId(), $participation)->verdict;
        if (null === $verdict->situation) {
            throw new NothingToEscalate();
        }
        $list->escalate($participation, $verdict->situation, \DateTimeImmutable::createFromInterface($this->clock->now()));

        return ['situation' => $verdict->situation->value];
    }
}
