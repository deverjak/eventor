<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Eventor\Shared\Domain\Tenant\TenantNotSet;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * ADR 005: appends `organizer_id = :current` to every query on a TenantScoped entity. The filter is always
 * enabled; without a tenant it throws TenantNotSet instead of returning an empty or foreign result.
 * TenantFilterSynchronizer sets the parameter; DBAL queries are not covered by Doctrine filters and take an
 * explicit OrganizerId instead.
 */
final class OrganizerFilter extends SQLFilter
{
    public const string NAME = 'organizer';
    public const string PARAMETER = 'organizerId';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        $reflection = $targetEntity->reflClass;
        if (null === $reflection || !$reflection->implementsInterface(TenantScoped::class)) {
            return '';
        }

        if (!$this->hasParameter(self::PARAMETER)) {
            throw TenantNotSet::forEntity($targetEntity->getName());
        }

        return \sprintf(
            '%s.%s = %s',
            $targetTableAlias,
            $targetEntity->getColumnName(self::PARAMETER),
            $this->getParameter(self::PARAMETER),
        );
    }
}
