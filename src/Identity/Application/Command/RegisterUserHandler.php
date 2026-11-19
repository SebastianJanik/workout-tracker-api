<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Aggregate\User;
use App\Identity\Domain\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\Repository\UserRepository;
use App\Identity\Domain\ValueObject\Email;
use App\SharedKernel\Domain\ValueObject\UserId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegisterUser $command): RegisteredUser
    {
        $email = Email::fromString($command->email);

        // Cheap early exit; the unique index (via UserRepository::add) is the real guard against races.
        if ($this->users->existsByEmail($email)) {
            throw EmailAlreadyRegistered::withEmail($email);
        }

        $user = User::register(
            UserId::fromString((string) Uuid::v7()),
            $email,
            $this->passwordHasher->hash($command->plainPassword),
            $this->clock->now(),
        );
        $this->users->add($user);

        return new RegisteredUser($user->id()->value(), $user->email()->value());
    }
}
