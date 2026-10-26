<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Http;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Identity\Contract\OrganizerLookup;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Registration\Application\PlaceOrder\FormTimeTrap;
use Eventor\Registration\Application\Query\PreviewSelection;
use Eventor\Registration\Application\Query\RegistrationPreviewQuery;
use Eventor\Registration\Contract\Exception\InvalidOrderRequest;
use Eventor\Registration\UserInterface\Http\Request\Payload;
use Eventor\Registration\UserInterface\Http\Request\PlaceOrderPayload;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Idempotency\IdempotencyKey;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\IdempotencyStamp;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\VariantId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The registration side of the public event page (PUB-01..04): the form for a chosen selection (REG-06, REG-05) and the
 * placing of the order (REG-01..03). No session; the organizer comes from the URL. Order creation is rate limited
 * (`PublicRateLimitListener`), protected by a honeypot and a minimum fill time, and idempotent on `Idempotency-Key`.
 */
#[AsController]
final class PublicOrderController extends AbstractController
{
    public function __construct(
        private readonly OrganizerLookup $organizers,
        private readonly EventCatalog $catalog,
        private readonly CommandBus $commands,
        private readonly RegistrationPreviewQuery $preview,
        private readonly FormTimeTrap $trap,
        private readonly AuditContext $audit,
    ) {}

    #[Route('/api/public/orgs/{orgSlug}/events/{eventSlug}/registration-form', name: 'api_public_registration_form', methods: ['POST'])]
    #[OA\Post(
        summary: 'The registration form for a chosen combination of activities and variants: fields per person, fields of the order contact, legal documents to confirm and by whom, plus the signed antispam timestamp',
        tags: ['public'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['items'],
            properties: [new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                required: ['activityId', 'variantId'],
                properties: [
                    new OA\Property(property: 'itemRef', type: 'string', example: 'p1'),
                    new OA\Property(property: 'activityId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'variantId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'minor', type: 'boolean', description: 'The participant is a minor: guardian requirements apply'),
                ],
                type: 'object',
            ))],
        )),
        responses: [
            new OA\Response(response: 200, description: 'formVersionId, orderFields, orderRequirements, items[] {itemRef, fields, requirements}, formToken'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 422, description: 'invalid_order_request'),
        ],
    )]
    public function form(string $orgSlug, string $eventSlug, Request $request): JsonResponse
    {
        $organizer = $this->organizer($orgSlug);
        $event = $this->catalog->resolvePublishedSlug($organizer->id, $eventSlug);

        return $this->json($this->preview->preview($organizer->id, $event, self::selection($request), true));
    }

    #[Route('/api/public/orgs/{orgSlug}/events/{eventSlug}/orders', name: 'api_public_order_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Place an order (REG-01..03, REG-06): N persons, M participations, one payment. Header Idempotency-Key is mandatory. The response has no payment data: instructions appear on the self-service page when they exist.',
        tags: ['public'],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['items', 'persons', 'orderAnswers'],
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'persons', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'orderAnswers', type: 'object'),
                new OA\Property(property: 'decisions', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'paymentMethod', type: 'string', enum: ['bank_transfer', 'cash', 'free']),
                new OA\Property(property: 'locale', type: 'string', example: 'cs'),
                new OA\Property(property: 'website', type: 'string', description: 'Honeypot: must stay empty'),
                new OA\Property(property: 'formToken', type: 'string', description: 'From the registration form endpoint'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'orderId, orderNumber, selfServiceUrl, payment.status (instructions_pending | cash_on_site | not_required)'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 409, description: 'capacity_exhausted | registration_closed | request_in_progress'),
            new OA\Response(response: 422, description: 'form_invalid | missing_confirmation | not_eligible | invalid_order_request | payment_method_not_allowed | spam_suspected | idempotency_key_reused | idempotency_key_required'),
            new OA\Response(response: 429, description: 'rate_limited'),
        ],
    )]
    public function place(string $orgSlug, string $eventSlug, Request $request, IdempotencyKey $key): JsonResponse
    {
        $organizer = $this->organizer($orgSlug);
        $body = self::body($request);

        $payload = PlaceOrderPayload::parse($body, $organizer->id, $this->catalog->resolvePublishedSlug($organizer->id, $eventSlug));
        $this->trap->assertHuman($payload->honeypot, $payload->formToken);

        $result = $this->audit->as(
            AuditActor::system('anonymous_public'),
            fn(): mixed => $this->commands->dispatch(
                $payload->command,
                TenantStamp::of($organizer->id),
                IdempotencyStamp::for('public_order:'.$organizer->id->toString().':'.$eventSlug, $key->value, PlaceOrderPayload::canonical($body)),
            ),
        );

        return $this->json($result, JsonResponse::HTTP_CREATED);
    }

    private function organizer(string $slug): OrganizerView
    {
        return $this->organizers->findBySlug($slug) ?? throw new NotFoundHttpException('Organizer not found.');
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function body(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            throw InvalidOrderRequest::at('body', 'invalid_json', 'The request body must be a JSON object.');
        }
    }

    /**
     * @return list<PreviewSelection>
     */
    private static function selection(Request $request): array
    {
        $selection = [];
        foreach (Payload::objects(self::body($request), 'items') as $i => $item) {
            $path = 'items.'.$i.'.';
            $selection[] = new PreviewSelection(
                Payload::string($item, 'itemRef', $path, false) ?? 'item'.$i,
                ActivityId::fromString((string) Payload::uuid($item, 'activityId', $path)),
                VariantId::fromString((string) Payload::uuid($item, 'variantId', $path)),
                true === ($item['minor'] ?? false),
            );
        }

        return $selection;
    }
}
