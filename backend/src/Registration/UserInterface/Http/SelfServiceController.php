<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Http;

use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Registration\Application\Cancellation\CancelParticipation;
use Eventor\Registration\Application\Confirm\RecordSelfServiceConfirmations;
use Eventor\Registration\Application\Correct\CorrectOrderData;
use Eventor\Registration\Application\Correct\CorrectPersonData;
use Eventor\Registration\Application\Query\SelfServiceOrderQuery;
use Eventor\Registration\Contract\CancelledBy;
use Eventor\Registration\Contract\Exception\InvalidOrderRequest;
use Eventor\Registration\UserInterface\Http\Request\Payload;
use Eventor\Registration\UserInterface\Security\SelfServicePrincipal;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The participant's side (REG-08, REG-09), authenticated by the self-service token (`Authorization: SelfService ss1_...`,
 * firewall `self_service`). The token is bound to one order: every id in the URL is checked against it, a foreign id is a
 * plain 404. The rules of the event (cancellation switched on, before the deadline) are judged by the commands.
 */
#[AsController]
final class SelfServiceController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly SelfServiceOrderQuery $orders,
    ) {}

    #[Route('/api/self-service/order', name: 'api_self_service_order', methods: ['GET'])]
    #[OA\Get(
        summary: 'The order behind the link: persons and participations with states, QR codes, open confirmations, payment state and instructions (REG-08)',
        tags: ['self-service'],
        security: [['SelfService' => []]],
        responses: [
            new OA\Response(response: 200, description: 'The order'),
            new OA\Response(response: 401, description: 'token_invalid'),
        ],
    )]
    public function order(): JsonResponse
    {
        $principal = $this->principal();

        return $this->json($this->orders->forOrder($principal->organizerId, $principal->orderId));
    }

    #[Route('/api/self-service/participations/{participationId}/cancel', name: 'api_self_service_cancel', methods: ['POST'], requirements: ['participationId' => Requirement::UUID])]
    #[OA\Post(
        summary: 'Cancel a participation of the order, if the rules of the event allow it (REG-09)',
        tags: ['self-service'],
        security: [['SelfService' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string')])),
        responses: [
            new OA\Response(response: 200, description: 'The updated order'),
            new OA\Response(response: 401, description: 'token_invalid'),
            new OA\Response(response: 404, description: 'participation_not_found (also a participation of another order)'),
            new OA\Response(response: 409, description: 'self_service_action_not_allowed | participation_transition_not_allowed'),
        ],
    )]
    public function cancel(string $participationId, Request $request): JsonResponse
    {
        $principal = $this->principal();
        $reason = $this->optionalReason($request);
        $this->commands->dispatch(new CancelParticipation($principal->organizerId, ParticipationId::fromString($participationId), CancelledBy::Participant, $reason, $principal->orderId));

        return $this->json($this->orders->forOrder($principal->organizerId, $principal->orderId));
    }

    #[Route('/api/self-service/persons/{personId}', name: 'api_self_service_correct_person', methods: ['PUT'], requirements: ['personId' => Requirement::UUID])]
    #[OA\Put(
        summary: 'Correct the answers of a person of the order (REG-09): the new answers replace the old ones as a whole',
        tags: ['self-service'],
        security: [['SelfService' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['answers'], properties: [new OA\Property(property: 'answers', type: 'object')])),
        responses: [
            new OA\Response(response: 200, description: 'The updated order'),
            new OA\Response(response: 401, description: 'token_invalid'),
            new OA\Response(response: 404, description: 'person_not_found'),
            new OA\Response(response: 422, description: 'form_invalid | not_eligible'),
        ],
    )]
    public function correctPerson(string $personId, Request $request): JsonResponse
    {
        $principal = $this->principal();
        $this->commands->dispatch(new CorrectPersonData($principal->organizerId, $principal->orderId, PersonId::fromString($personId), Payload::object($this->body($request), 'answers')));

        return $this->json($this->orders->forOrder($principal->organizerId, $principal->orderId));
    }

    #[Route('/api/self-service/contact', name: 'api_self_service_correct_contact', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Correct the contact of the order, the order-scoped answers of the form (REG-09), e.g. a typo in the e-mail',
        tags: ['self-service'],
        security: [['SelfService' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['answers'], properties: [new OA\Property(property: 'answers', type: 'object')])),
        responses: [
            new OA\Response(response: 200, description: 'The updated order'),
            new OA\Response(response: 401, description: 'token_invalid'),
            new OA\Response(response: 422, description: 'form_invalid'),
        ],
    )]
    public function correctContact(Request $request): JsonResponse
    {
        $principal = $this->principal();
        $this->commands->dispatch(new CorrectOrderData($principal->organizerId, $principal->orderId, Payload::object($this->body($request), 'answers')));

        return $this->json($this->orders->forOrder($principal->organizerId, $principal->orderId));
    }

    #[Route('/api/self-service/confirmations', name: 'api_self_service_confirmations', methods: ['POST'])]
    #[OA\Post(
        summary: 'Confirm (or, for a revocable consent, withdraw) legal requirements of the order for their current document version (S15, LEG-09)',
        tags: ['self-service'],
        security: [['SelfService' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['decisions'],
            properties: [new OA\Property(property: 'decisions', type: 'array', items: new OA\Items(
                required: ['requirementId', 'decision'],
                properties: [
                    new OA\Property(property: 'requirementId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'participationId', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'decision', type: 'string', enum: ['granted', 'declined', 'withdrawn']),
                ],
                type: 'object',
            ))],
        )),
        responses: [
            new OA\Response(response: 200, description: 'The updated order'),
            new OA\Response(response: 401, description: 'token_invalid'),
            new OA\Response(response: 422, description: 'invalid_order_request | confirmation_not_allowed'),
        ],
    )]
    public function confirmations(Request $request): JsonResponse
    {
        $principal = $this->principal();
        $decisions = [];
        foreach (Payload::objects($this->body($request), 'decisions') as $i => $item) {
            $path = 'decisions.'.$i.'.';
            $decision = ConsentDecision::tryFrom((string) Payload::string($item, 'decision', $path));
            if (null === $decision || ConsentDecision::Requested === $decision) {
                throw InvalidOrderRequest::at($path.'decision', 'invalid_decision', 'The decision is "granted", "declined" or "withdrawn".');
            }
            $participation = Payload::uuid($item, 'participationId', $path, false);
            $decisions[] = [
                'requirementId' => LegalRequirementId::fromString((string) Payload::uuid($item, 'requirementId', $path)),
                'participationId' => null === $participation ? null : ParticipationId::fromString($participation),
                'decision' => $decision,
            ];
        }
        $this->commands->dispatch(new RecordSelfServiceConfirmations($principal->organizerId, $principal->orderId, $decisions));

        return $this->json($this->orders->forOrder($principal->organizerId, $principal->orderId));
    }

    private function principal(): SelfServicePrincipal
    {
        $user = $this->getUser();

        return $user instanceof SelfServicePrincipal ? $user : throw new NotFoundHttpException('No self-service token.');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function body(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            throw InvalidOrderRequest::at('body', 'invalid_json', 'The request body must be a JSON object.');
        }
    }

    private function optionalReason(Request $request): ?string
    {
        if ('' === $request->getContent()) {
            return null;
        }
        $reason = $this->body($request)['reason'] ?? null;

        return \is_string($reason) ? mb_substr($reason, 0, 500) : null;
    }
}
