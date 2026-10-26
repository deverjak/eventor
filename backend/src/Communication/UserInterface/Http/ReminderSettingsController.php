<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http;

use Eventor\Communication\Application\Query\ReminderSettingsQuery;
use Eventor\Communication\Application\Reminder\ChangeReminderSettings;
use Eventor\Communication\UserInterface\Http\Request\ReminderSettingsRequest;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Messaging\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PAY-09: the configurable reminder schedule (hours relative to the due date of a payment, hours before an activity).
 */
#[AsController]
#[RequiresPermission(Permission::OrganizerSettingsManage)]
final class ReminderSettingsController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly ReminderSettingsQuery $settings,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/communication/reminder-settings', name: 'api_admin_reminder_settings_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'The reminder schedule (the defaults until it is changed)',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: '{paymentOffsetsHours, preEventHours, custom}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
        ],
    )]
    public function get(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return $this->json($this->settings->get($organizer->id));
    }

    #[Route('/api/admin/org/{orgSlug}/communication/reminder-settings', name: 'api_admin_reminder_settings_put', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Changes the reminder schedule; applies to reminders planned from now on',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: 'The schedule after the change'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 422, description: 'validation_failed or invalid_reminder_settings'),
        ],
    )]
    public function put(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] ReminderSettingsRequest $body): JsonResponse
    {
        $this->commands->dispatch(new ChangeReminderSettings($body->paymentOffsetsHours, $body->preEventHours));

        return $this->json($this->settings->get($organizer->id));
    }
}
