<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Shared\Application\Tenant\TenantContext;

/**
 * Makes the Doctrine `organizer` filter follow the TenantContext. Re-enabling the filter creates a fresh instance
 * without parameters, which is how "no tenant" is expressed (the filter then throws TenantNotSet).
 */
final readonly class TenantFilterSynchronizer
{
    public function __construct(
        private EntityManagerInterface $em,
        private TenantContext $tenant,
    ) {}

    public function sync(): void
    {
        $filters = $this->em->getFilters();
        if ($filters->isEnabled(OrganizerFilter::NAME)) {
            $filters->disable(OrganizerFilter::NAME);
        }

        $filter = $filters->enable(OrganizerFilter::NAME);
        $current = $this->tenant->current();
        if (null !== $current) {
            $filter->setParameter(OrganizerFilter::PARAMETER, $current->toString(), 'string');
        }
    }
}
