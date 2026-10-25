<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Audit;

/**
 * Who caused a change (AUD-02, AUD-03). A user is identified by the account id (never by e-mail), a system actor
 * by the name of the job or subscriber, so audit can tell "the expiry sweeper" from "Jana".
 */
final readonly class AuditActor
{
    public function __construct(
        public ActorType $type,
        public ?string $id = null,
        public ?string $label = null,
    ) {}

    public static function user(string $accountId): self
    {
        return new self(ActorType::User, $accountId);
    }

    public static function system(string $name): self
    {
        return new self(ActorType::System, null, $name);
    }

    public static function pairrot(): self
    {
        return new self(ActorType::Pairrot, null, 'pairrot');
    }
}
