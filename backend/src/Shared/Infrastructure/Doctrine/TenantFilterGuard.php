<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

/**
 * The one sanctioned way to read across tenants (webhook lookup by Pairrot id, expiry sweepers, ...): the filter is
 * switched off for the callback, the reason is logged and the filter state is restored. An architecture test lists
 * the call sites, so a new one cannot slip through review.
 */
#[WithMonologChannel('audit_ops')]
final readonly class TenantFilterGuard
{
    public function __construct(
        private EntityManagerInterface $em,
        private TenantFilterSynchronizer $synchronizer,
        private LoggerInterface $logger,
    ) {}

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function crossTenant(string $reason, callable $work): mixed
    {
        $this->logger->info('Cross-tenant access.', ['reason' => $reason]);

        $filters = $this->em->getFilters();
        if ($filters->isEnabled(OrganizerFilter::NAME)) {
            $filters->disable(OrganizerFilter::NAME);
        }

        try {
            return $work();
        } finally {
            $this->synchronizer->sync();
        }
    }
}
