<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure;

use App\Identity\Domain\Aggregate\User;
use App\Identity\Domain\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\Repository\UserRepository;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\SharedKernel\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Talks to the real test database (each test is rolled back by DAMA).
 */
final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private UserRepository $users;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $this->users = $users;
    }

    public function testStoresAndFindsAUserByEmailIgnoringCase(): void
    {
        $user = $this->newUser('anna@example.com');
        $this->users->add($user);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $found = $this->users->getByEmail(Email::fromString('ANNA@example.com'));

        self::assertTrue($found->id()->equals($user->id()));
        self::assertSame('hash', $found->password()->value());
        self::assertSame(['ROLE_USER'], $found->roles());
        self::assertTrue($this->users->existsByEmail(Email::fromString('anna@example.com')));
    }

    public function testUnknownEmailThrows(): void
    {
        self::assertFalse($this->users->existsByEmail(Email::fromString('nobody@example.com')));

        $this->expectException(UserNotFound::class);

        $this->users->getByEmail(Email::fromString('nobody@example.com'));
    }

    public function testTheUniqueIndexStopsADuplicateEvenWithoutThePreCheck(): void
    {
        $this->users->add($this->newUser('anna@example.com'));

        $this->expectException(EmailAlreadyRegistered::class);

        $this->users->add($this->newUser('anna@example.com'));
    }

    private function newUser(string $email): User
    {
        return User::register(
            UserId::fromString((string) Uuid::v7()),
            Email::fromString($email),
            HashedPassword::fromHash('hash'),
            new \DateTimeImmutable('2026-11-19 10:00:00'),
        );
    }
}
