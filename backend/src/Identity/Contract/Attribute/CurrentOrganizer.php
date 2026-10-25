<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract\Attribute;

use Symfony\Component\HttpKernel\Attribute\ValueResolver;

/**
 * Controller argument: the organizer named by the `{orgSlug}` route parameter, if the signed-in user is a member.
 * Otherwise the request ends with 404 (not 403: foreign tenants must not be discoverable). Also activates the
 * tenant for the rest of the request (TenantContext + Doctrine `organizer` filter).
 *
 *     public function __invoke(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class CurrentOrganizer extends ValueResolver
{
    public const string RESOLVER = 'current_organizer';

    public function __construct(bool $disabled = false)
    {
        parent::__construct(self::RESOLVER, $disabled);
    }
}
