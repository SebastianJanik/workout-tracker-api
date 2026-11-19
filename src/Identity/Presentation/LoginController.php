<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Declares POST /api/auth/login. The `login` firewall's json_login authenticator answers the request
 * before this action runs (Symfony requires a route to exist for the check_path).
 */
final class LoginController
{
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    #[OA\Post(operationId: 'login', summary: 'Log in with email and password', security: [], tags: ['Authentication'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property('email', type: 'string'), new OA\Property('password', type: 'string')]))]
    #[OA\Response(response: 200, description: 'JWT and refresh token.', content: new OA\JsonContent(properties: [new OA\Property('token', type: 'string'), new OA\Property('refresh_token', type: 'string')]))]
    #[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
    public function __invoke(): never
    {
        throw new \LogicException('Handled by the json_login authenticator in the "login" firewall.');
    }
}
