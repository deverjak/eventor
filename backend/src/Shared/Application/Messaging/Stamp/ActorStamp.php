<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Messaging\Stamp;

use Eventor\Shared\Domain\Audit\ActorType;
use Eventor\Shared\Domain\Audit\AuditActor;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Who dispatched the message; restored in the worker so audit entries keep the original actor.
 */
final readonly class ActorStamp implements StampInterface
{
    public function __construct(
        public string $type,
        public ?string $id = null,
        public ?string $label = null,
    ) {}

    public static function of(AuditActor $actor): self
    {
        return new self($actor->type->value, $actor->id, $actor->label);
    }

    public function actor(): AuditActor
    {
        return new AuditActor(ActorType::from($this->type), $this->id, $this->label);
    }
}
