<?php

declare(strict_types=1);

namespace Eventor\Attention\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Eventor\Shared\Application\Attention\AttentionRaiser;
use Eventor\Shared\Application\Attention\AttentionRequest;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;

/**
 * Opens an attention item with a single idempotent INSERT: the same cause (dedupe key) never creates a second
 * item. Works outside a command transaction (the dead-letter listener runs in the worker after a failure) and
 * independently of the state of the Doctrine EntityManager.
 */
#[AsAlias(AttentionRaiser::class)]
final readonly class DbalAttentionRaiser implements AttentionRaiser
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {}

    public function raise(AttentionRequest $request): void
    {
        $this->connection->executeStatement(
            'INSERT INTO attention.attention_item
                (id, organizer_id, type, dedupe_key, subject_type, subject_id, state, payload, created_at)
             VALUES
                (:id, :organizer, :type, :dedupe, :subject_type, :subject_id, \'OPEN\', CAST(:payload AS jsonb), :created_at)
             ON CONFLICT (dedupe_key) DO NOTHING',
            [
                'id' => Uuid::v7()->toRfc4122(),
                'organizer' => $request->organizerId?->toString(),
                'type' => $request->type->value,
                'dedupe' => $request->dedupeKey,
                'subject_type' => $request->subjectType,
                'subject_id' => $request->subjectId,
                'payload' => json_encode($request->payload, \JSON_THROW_ON_ERROR),
                'created_at' => \DateTimeImmutable::createFromInterface($this->clock->now())->format('Y-m-d H:i:s.uP'),
            ],
        );
    }
}
