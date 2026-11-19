<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Application\Command\RegisteredUser;
use App\Identity\Application\Command\RegisterUser;
use App\Identity\Presentation\Request\RegisterRequest;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController extends AbstractController
{
    use HandleTrait;

    // The un-targeted MessageBusInterface is the default bus, i.e. `command.bus` (messenger.yaml).
    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    #[OA\Post(operationId: 'register', summary: 'Create an account', security: [], tags: ['Authentication'])]
    #[OA\Response(response: 201, description: 'Account created.', content: new OA\JsonContent(
        required: ['id', 'email'],
        properties: [new OA\Property('id', type: 'string', format: 'uuid'), new OA\Property('email', type: 'string', format: 'email')],
    ))]
    #[OA\Response(response: 409, description: 'The email is already registered.')]
    #[OA\Response(response: 422, description: 'Invalid email or password (violations per field).')]
    public function __invoke(#[MapRequestPayload] RegisterRequest $request): JsonResponse
    {
        /** @var RegisteredUser $user */
        $user = $this->handle(new RegisterUser($request->email, $request->password));

        return $this->json(['id' => $user->id, 'email' => $user->email], Response::HTTP_CREATED);
    }
}
