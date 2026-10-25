<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Doctrine;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\Payments\Domain\Inbox\InboxMessage;
use Eventor\Payments\Domain\Inbox\InboxMessageRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(InboxMessageRepository::class)]
final readonly class DoctrineInboxMessageRepository implements InboxMessageRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function lock(string $id): ?InboxMessage
    {
        $message = $this->em->find(InboxMessage::class, $id, LockMode::PESSIMISTIC_WRITE);
        if (null !== $message) {
            $this->em->refresh($message, LockMode::PESSIMISTIC_WRITE);
        }

        return $message;
    }
}
