<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Application\Query\GetUserProfile;
use App\Identity\Application\Query\UserProfile;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class CurrentUserController extends AbstractController
{
    use HandleTrait;

    public function __construct(#[Target('query.bus')] MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[OA\Get(operationId: 'me', summary: 'The authenticated user', tags: ['Authentication'])]
    #[OA\Response(response: 200, description: 'The current user.', content: new OA\JsonContent(
        required: ['id', 'email'],
        properties: [new OA\Property('id', type: 'string', format: 'uuid'), new OA\Property('email', type: 'string', format: 'email')],
    ))]
    #[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
    public function __invoke(#[CurrentUser] UserInterface $user): JsonResponse
    {
        /** @var UserProfile $profile */
        $profile = $this->handle(new GetUserProfile($user->getUserIdentifier()));

        return $this->json(['id' => $profile->id, 'email' => $profile->email]);
    }
}
