<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/**
 * Selects the stateless `check_in` firewall: a request under `/api/check-in` that carries a device token
 * (`Authorization: Bearer dev1_...`). Every other request to that path (a signed-in team member with the session
 * cookie) falls through to the `team` firewall, which is how "device or team member" works without ever writing a
 * device principal into a session.
 */
final readonly class DeviceRequestMatcher implements RequestMatcherInterface
{
    public function matches(Request $request): bool
    {
        $path = $request->getPathInfo();
        if ('/api/check-in' !== $path && !str_starts_with($path, '/api/check-in/')) {
            return false;
        }

        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer dev1_');
    }
}
