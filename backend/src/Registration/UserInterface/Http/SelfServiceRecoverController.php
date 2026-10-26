<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Http;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Identity\Contract\OrganizerLookup;
use Eventor\Registration\Application\Recover\RequestSelfServiceLink;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Id\EventId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * REG-11: "send me my link again". Always `202` with the same body, whether or not the address, the organizer or the event
 * exists and whether or not a limit was hit, so the endpoint cannot be used to find out who registered.
 */
#[AsController]
final class SelfServiceRecoverController extends AbstractController
{
    public function __construct(
        private readonly OrganizerLookup $organizers,
        private readonly EventCatalog $catalog,
        private readonly CommandBus $commands,
        private readonly AuditContext $audit,
        #[Autowire(service: 'limiter.registration_recover_email')]
        private readonly RateLimiterFactoryInterface $perEmail,
        #[Autowire(service: 'limiter.registration_recover_ip')]
        private readonly RateLimiterFactoryInterface $perIp,
    ) {}

    #[Route('/api/public/self-service/recover', name: 'api_public_self_service_recover', methods: ['POST'])]
    #[OA\Post(
        summary: 'Request a new self-service link by e-mail (REG-11). Always 202, never reveals whether the address is known.',
        tags: ['public'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'orgSlug'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'orgSlug', type: 'string'),
                new OA\Property(property: 'eventSlug', type: 'string'),
            ],
        )),
        responses: [
            new OA\Response(response: 202, description: 'accepted'),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $response = new JsonResponse(['status' => 'accepted'], JsonResponse::HTTP_ACCEPTED);

        if (!$this->perIp->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return $response; // over the limit: the same answer, no work (nothing to tell an enumerator)
        }

        try {
            $body = $request->toArray();
        } catch (\Throwable) {
            return $response;
        }
        $email = \is_string($body['email'] ?? null) ? mb_strtolower(trim($body['email'])) : '';
        $slug = \is_string($body['orgSlug'] ?? null) ? $body['orgSlug'] : '';
        if ('' === $email || '' === $slug || !$this->perEmail->create($slug.'|'.$email)->consume()->isAccepted()) {
            return $response;
        }

        $organizer = $this->organizers->findBySlug($slug);
        if (null === $organizer) {
            return $response;
        }

        $event = null;
        if (\is_string($body['eventSlug'] ?? null) && '' !== $body['eventSlug']) {
            try {
                $event = $this->catalog->resolvePublishedSlug($organizer->id, $body['eventSlug']);
            } catch (EventNotFound) {
                return $response;
            }
        }

        $this->audit->as(AuditActor::system('anonymous_public'), fn(): mixed => $this->commands->dispatch(
            new RequestSelfServiceLink($organizer->id, $email, $event instanceof EventId ? $event : null),
            TenantStamp::of($organizer->id),
        ));

        return $response;
    }
}
