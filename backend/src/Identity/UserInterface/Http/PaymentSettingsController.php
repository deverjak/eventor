<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http;

use Eventor\Identity\Application\ChangePolicy\ChangeOrganizerPolicy;
use Eventor\Identity\Application\ConnectPairrot\ConnectOrganizerToPairrot;
use Eventor\Identity\Application\Query\OrganizerAdminQuery;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\UserInterface\Http\Request\ConnectPairrotRequest;
use Eventor\Identity\UserInterface\Http\Request\PaymentSettingsRequest;
use Eventor\Shared\Application\Messaging\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * ORG-02: payment defaults of the organizer (due days, reservation TTL, auto-restore of late payments, overpayment
 * handling, the two policy exceptions of the permission matrix) and the Pairrot onboarding.
 */
#[AsController]
final class PaymentSettingsController extends AbstractController
{
    public function __construct(
        private readonly OrganizerAdminQuery $query,
        private readonly CommandBus $commands,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/payment-settings', name: 'api_admin_payment_settings', methods: ['GET'])]
    #[RequiresPermission(Permission::OrganizerSettingsManage)]
    #[OA\Get(
        summary: 'Payment settings: defaults, policy exceptions and the Pairrot connection state',
        tags: ['organizer'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'The payment settings'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: owner only'),
            new OA\Response(response: 404, description: 'not_found'),
        ],
    )]
    public function show(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return $this->json($this->query->paymentSettings($organizer->id));
    }

    #[Route('/api/admin/org/{orgSlug}/payment-settings', name: 'api_admin_payment_settings_update', methods: ['PUT'])]
    #[RequiresPermission(Permission::OrganizerSettingsManage)]
    #[OA\Put(
        summary: 'Replace the payment defaults and policy exceptions',
        tags: ['organizer'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'defaultDueDays', type: 'integer', minimum: 1, maximum: 365, example: 14),
            new OA\Property(property: 'defaultHoldTtlHours', type: 'integer', minimum: 1, maximum: 720, example: 48),
            new OA\Property(property: 'autoRestoreLatePayment', type: 'boolean'),
            new OA\Property(property: 'overpaymentHandling', type: 'string', enum: ['refund', 'donation', 'ask']),
            new OA\Property(property: 'checkInMayRecordCash', type: 'boolean'),
            new OA\Property(property: 'eventManagerMayResolvePayments', type: 'boolean'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'The saved settings'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: owner only'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function update(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] PaymentSettingsRequest $body): JsonResponse
    {
        $this->commands->dispatch(new ChangeOrganizerPolicy(
            $organizer->id,
            $body->defaultDueDays,
            $body->defaultHoldTtlHours,
            $body->autoRestoreLatePayment,
            $body->overpaymentHandling,
            $body->checkInMayRecordCash,
            $body->eventManagerMayResolvePayments,
        ));

        return $this->json($this->query->paymentSettings($organizer->id));
    }

    #[Route('/api/admin/org/{orgSlug}/payment-settings/pairrot', name: 'api_admin_payment_settings_pairrot', methods: ['POST'])]
    #[RequiresPermission(Permission::OrganizerSettingsManage)]
    #[OA\Post(
        summary: 'Connect the bank account through Pairrot (activates the subtenant, connects the account). Repeatable.',
        tags: ['organizer'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['accountNumberOrIban'],
            properties: [
                new OA\Property(property: 'accountNumberOrIban', type: 'string'),
                new OA\Property(property: 'label', type: 'string'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Connected: the payment settings plus the Pairrot inbound address'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: owner only'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 409, description: 'bank_account_unavailable: connected elsewhere'),
            new OA\Response(response: 422, description: 'validation_failed or contact_email_required'),
            new OA\Response(response: 503, description: 'pairrot_unavailable'),
        ],
    )]
    public function connectPairrot(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] ConnectPairrotRequest $body): JsonResponse
    {
        /** @var array{inboundAddress: string|null} $connection */
        $connection = $this->commands->dispatch(new ConnectOrganizerToPairrot($organizer->id, $body->accountNumberOrIban, $body->label));

        return $this->json(['settings' => $this->query->paymentSettings($organizer->id), 'inboundAddress' => $connection['inboundAddress']]);
    }
}
