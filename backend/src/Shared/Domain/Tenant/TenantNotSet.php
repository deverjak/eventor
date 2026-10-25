<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Tenant;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * A tenant-scoped entity was queried without an active tenant. It is an error, never an empty or foreign result.
 */
#[AsProblem(code: 'tenant_not_set', status: 500)]
final class TenantNotSet extends DomainException
{
    public static function forContext(): self
    {
        return new self('No tenant is active in this context.');
    }

    public static function forEntity(string $entityClass): self
    {
        return new self(\sprintf('Entity %s is tenant-scoped but no tenant is active.', $entityClass));
    }
}
