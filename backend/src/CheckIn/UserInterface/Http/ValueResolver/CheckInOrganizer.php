<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http\ValueResolver;

use Symfony\Component\HttpKernel\Attribute\ValueResolver;

/**
 * Controller argument: the organizer of a check-in request, for both ways in. A team member names it by the
 * `{orgSlug}` route parameter (and must be a member, else 404); a device token belongs to exactly one organizer, so the
 * routes without a slug work for it. Either way the tenant is activated for the rest of the request.
 *
 *     public function __invoke(#[CheckInOrganizer] OrganizerView $organizer, ...): JsonResponse
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class CheckInOrganizer extends ValueResolver
{
    public const string RESOLVER = 'check_in_organizer';

    public function __construct(bool $disabled = false)
    {
        parent::__construct(self::RESOLVER, $disabled);
    }
}
