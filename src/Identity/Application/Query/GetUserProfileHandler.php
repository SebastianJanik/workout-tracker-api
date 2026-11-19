<?php

declare(strict_types=1);

namespace App\Identity\Application\Query;

use App\Identity\Domain\Repository\UserRepository;
use App\Identity\Domain\ValueObject\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetUserProfileHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(GetUserProfile $query): UserProfile
    {
        $user = $this->users->getByEmail(Email::fromString($query->email));

        return new UserProfile($user->id()->value(), $user->email()->value());
    }
}
