<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Messenger;

use Eventor\Identity\Contract\Event\MembershipChanged;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Tenant\TenantContext;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Test subscriber on the Doctrine transport `probe_outbox`: proves delivery, the restored tenant and the actor.
 */
#[AsMessageHandler(fromTransport: 'probe_outbox')]
final readonly class ProbeSubscriber
{
    public function __construct(
        private ProbeRecorder $recorder,
        private TenantContext $tenant,
        private AuditContext $audit,
    ) {}

    public function __invoke(MembershipChanged $event): void
    {
        $this->recorder->received('ok', $event, $this->tenant->current()?->toString(), $this->audit->actor()?->label);
    }
}
