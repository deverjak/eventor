<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Id;

/**
 * Tenant identifier: every tenant-scoped row, message and audit entry carries one (ADR 005).
 */
final readonly class OrganizerId extends AbstractId {}
