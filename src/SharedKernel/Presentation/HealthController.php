<?php

declare(strict_types=1);

namespace App\SharedKernel\Presentation;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    #[OA\Get(operationId: 'health', summary: 'Liveness check', security: [], tags: ['System'])]
    #[OA\Response(response: 200, description: 'The app is up.', content: new OA\JsonContent(
        required: ['status'],
        properties: [new OA\Property('status', type: 'string', example: 'ok')],
    ))]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
