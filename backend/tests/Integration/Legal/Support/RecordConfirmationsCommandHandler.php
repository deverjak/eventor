<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal\Support;

use Eventor\Legal\Contract\ConfirmationRecorder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RecordConfirmationsCommandHandler
{
    public function __construct(private ConfirmationRecorder $recorder) {}

    /**
     * @return list<string>
     */
    public function __invoke(RecordConfirmationsCommand $command): array
    {
        return array_map(
            static fn($id): string => $id->toString(),
            $this->recorder->record($command->organizerId, $command->confirmations),
        );
    }
}
