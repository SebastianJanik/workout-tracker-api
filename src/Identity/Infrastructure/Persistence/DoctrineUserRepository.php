<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Aggregate\User;
use App\Identity\Domain\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\Repository\UserRepository;
use App\Identity\Domain\ValueObject\Email;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(User $user): void
    {
        try {
            $this->entityManager->persist($user);
            // Flush now (inside the command bus transaction) so a race on the unique index
            // surfaces here as a domain exception, not later at commit.
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw EmailAlreadyRegistered::withEmail($user->email());
        }
    }

    public function existsByEmail(Email $email): bool
    {
        return null !== $this->entityManager->getRepository(User::class)->findOneBy(['email.value' => $email->value()]);
    }

    public function getByEmail(Email $email): User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email.value' => $email->value()])
            ?? throw UserNotFound::withEmail($email);
    }
}
