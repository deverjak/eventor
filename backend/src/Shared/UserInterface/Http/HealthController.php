<?php

declare(strict_types=1);

namespace Eventor\Shared\UserInterface\Http;

use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class HealthController extends AbstractController
{
    public function __construct(private readonly Connection $connection) {}

    #[Route('/api/health/live', name: 'api_health_live', methods: ['GET'])]
    #[OA\Get(summary: 'Liveness: the process answers', tags: ['system'], responses: [new OA\Response(response: 200, description: 'Alive')])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/api/health/ready', name: 'api_health_ready', methods: ['GET'])]
    #[OA\Get(summary: 'Readiness: the database answers', tags: ['system'], responses: [
        new OA\Response(response: 200, description: 'Ready'),
        new OA\Response(response: 503, description: 'Database unavailable'),
    ])]
    public function ready(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
        } catch (\Throwable) {
            return new JsonResponse(['status' => 'unavailable', 'database' => 'down'], 503);
        }

        return new JsonResponse(['status' => 'ok', 'database' => 'up']);
    }
}
