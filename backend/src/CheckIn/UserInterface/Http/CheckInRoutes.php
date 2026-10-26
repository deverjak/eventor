<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http;

/**
 * The two ways into the check-in API (ADR 006): a team member names the organizer in the URL, a device token already
 * belongs to one. Every action answers on both.
 */
final class CheckInRoutes
{
    public const string TEAM = '/api/check-in/org/{orgSlug}/events/{eventId}';
    public const string DEVICE = '/api/check-in/events/{eventId}';
}
