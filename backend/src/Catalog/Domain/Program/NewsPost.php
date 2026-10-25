<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Program;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Catalog\Domain\Event\NewsPostModified;
use Eventor\Catalog\Domain\Exception\NewsPostAlreadyPublished;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\NewsPostId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * An announcement of an event (PRG-04): text, priority, author and the choice to also send it by e-mail. A draft is
 * edited freely; a published post is final (readers and e-mails already have it).
 */
#[ORM\Entity]
#[ORM\Table(name: 'news_post', schema: 'catalog')]
class NewsPost implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'string', length: 200)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(type: 'integer')]
    private int $priority;

    #[ORM\Column(type: 'boolean')]
    private bool $broadcast;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'news_post_id')]
        private NewsPostId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        #[ORM\Column(type: 'user_account_id', nullable: true)]
        private ?UserAccountId $authorAccountId,
        string $title,
        string $body,
        int $priority,
        bool $broadcast,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->title = $title;
        $this->body = $body;
        $this->priority = $priority;
        $this->broadcast = $broadcast;
        $this->updatedAt = $createdAt;
    }

    public static function draft(NewsPostId $id, OrganizerId $organizerId, EventId $eventId, ?UserAccountId $author, string $title, string $body, int $priority, bool $broadcast, \DateTimeImmutable $now): self
    {
        $post = new self($id, $organizerId, $eventId, $author, $title, $body, $priority, $broadcast, $now);
        $post->record(new NewsPostModified($organizerId, $eventId, $id, 'created', $broadcast));

        return $post;
    }

    public function id(): NewsPostId
    {
        return $this->id;
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function authorAccountId(): ?UserAccountId
    {
        return $this->authorAccountId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }

    /**
     * @throws NewsPostAlreadyPublished
     */
    public function edit(string $title, string $body, int $priority, bool $broadcast, \DateTimeImmutable $now): void
    {
        if ($this->isPublished()) {
            throw NewsPostAlreadyPublished::create();
        }
        $this->title = $title;
        $this->body = $body;
        $this->priority = $priority;
        $this->broadcast = $broadcast;
        $this->updatedAt = $now;
        $this->record(new NewsPostModified($this->organizerId, $this->eventId, $this->id, 'updated', $broadcast));
    }

    /**
     * @throws NewsPostAlreadyPublished
     */
    public function publish(\DateTimeImmutable $now): void
    {
        if ($this->isPublished()) {
            throw NewsPostAlreadyPublished::create();
        }
        $this->publishedAt = $now;
        $this->updatedAt = $now;
        $this->record(new NewsPostModified($this->organizerId, $this->eventId, $this->id, 'published', $this->broadcast));
    }
}
