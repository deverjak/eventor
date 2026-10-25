<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Eventor\Catalog\Domain\Program\NewsPostRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * PRG-04: publishes a news post; `catalog.news_published` follows (`broadcast` = also by e-mail).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class PublishNewsPostHandler
{
    public function __construct(
        private EventRepository $events,
        private NewsPostRepository $posts,
        private ClockInterface $clock,
    ) {}

    public function __invoke(PublishNewsPost $command): void
    {
        $event = $this->events->get($command->eventId);
        $this->posts->get($event->id(), $command->newsPostId)->publish(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
