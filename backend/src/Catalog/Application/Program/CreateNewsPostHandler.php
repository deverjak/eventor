<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Eventor\Catalog\Domain\Program\NewsPost;
use Eventor\Catalog\Domain\Program\NewsPostRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Domain\Id\NewsPostId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * PRG-04: creates a news post (a draft, or published at once). The author is the signed-in member.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateNewsPostHandler
{
    public function __construct(
        private EventRepository $events,
        private NewsPostRepository $posts,
        private AuditContext $audit,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{newsPostId: string, published: bool}
     */
    public function __invoke(CreateNewsPost $command): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $event = $this->events->get($command->eventId);
        $actor = $this->audit->actor();
        $author = null !== $actor && null !== $actor->id && UserAccountId::isValid($actor->id) ? UserAccountId::fromString($actor->id) : null;

        $input = $command->input;
        $post = NewsPost::draft(NewsPostId::generate(), $event->organizerId(), $event->id(), $author, trim($input->title), $input->body, $input->priority, $input->broadcast, $now);
        $this->posts->add($post);
        if ($input->publish) {
            $post->publish($now);
        }

        return ['newsPostId' => $post->id()->toString(), 'published' => $post->isPublished()];
    }
}
