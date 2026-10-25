<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Eventor\Catalog\Domain\Program\NewsPostRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Edits a draft news post; with `publish` it is published right after.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateNewsPostHandler
{
    public function __construct(
        private EventRepository $events,
        private NewsPostRepository $posts,
        private ClockInterface $clock,
    ) {}

    public function __invoke(UpdateNewsPost $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $event = $this->events->get($command->eventId);
        $post = $this->posts->get($event->id(), $command->newsPostId);
        $input = $command->input;
        $post->edit(trim($input->title), $input->body, $input->priority, $input->broadcast, $now);
        if ($input->publish) {
            $post->publish($now);
        }
    }
}
