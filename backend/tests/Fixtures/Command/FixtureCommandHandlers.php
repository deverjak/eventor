<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Command;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Identity\Domain\Organizer\Organizer;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handlers of the fixture commands used by CommandEmitsEventTest.
 */
final readonly class FixtureCommandHandlers
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {}

    #[AsMessageHandler(bus: 'command.bus')]
    public function emits(EmitsEvent $command): string
    {
        $organizer = Organizer::register(OrganizerId::generate(), $command->slug, 'Fixture', \DateTimeImmutable::createFromInterface($this->clock->now()));
        $this->em->persist($organizer);

        return $organizer->id()->toString();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function nothing(EmitsNothing $command): void {}

    #[AsMessageHandler(bus: 'command.bus')]
    public function reads(ReadsOnly $command): void {}
}
