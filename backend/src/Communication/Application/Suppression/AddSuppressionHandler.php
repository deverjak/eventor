<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Suppression;

use Eventor\Communication\Domain\Suppression\Suppression;
use Eventor\Communication\Domain\Suppression\SuppressionId;
use Eventor\Communication\Domain\Suppression\SuppressionRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Suppressing an address that is already suppressed in this scope changes nothing.')]
final readonly class AddSuppressionHandler
{
    public function __construct(
        private SuppressionRepository $suppressions,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{suppressionId: string}
     */
    public function __invoke(AddSuppression $command): array
    {
        $hash = self::hash($command->email);
        $existing = $this->suppressions->findByHash($hash, $command->scope);
        if (null !== $existing) {
            return ['suppressionId' => $existing->id()->toString()];
        }

        $suppression = Suppression::add(
            SuppressionId::generate(),
            $this->tenant->require(),
            $hash,
            $command->scope,
            $command->reason,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        $this->suppressions->add($suppression);

        return ['suppressionId' => $suppression->id()->toString()];
    }

    public static function hash(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }
}
