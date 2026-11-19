<?php

declare(strict_types=1);

namespace App\Tests\Identity\Application;

use App\Identity\Application\Command\RegisterUser;
use App\Identity\Application\Command\RegisterUserHandler;
use App\Identity\Domain\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\Exception\InvalidEmail;
use App\Identity\Domain\Repository\UserRepository;
use App\Identity\Domain\ValueObject\Email;
use App\SharedKernel\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Real handler, real repository, real hasher, real test database (rolled back by DAMA).
 */
final class RegisterUserHandlerTest extends KernelTestCase
{
    private RegisterUserHandler $handler;
    private UserRepository $users;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var RegisterUserHandler $handler */
        $handler = self::getContainer()->get(RegisterUserHandler::class);
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $this->handler = $handler;
        $this->users = $users;
    }

    public function testRegistersAUserWithAHashedPasswordAndNormalisedEmail(): void
    {
        $result = $this->handler->__invoke(new RegisterUser('  Anna@Example.com ', 'a-long-password'));

        self::assertSame('anna@example.com', $result->email);
        self::assertSame($result->id, UserId::fromString($result->id)->value());

        $user = $this->users->getByEmail(Email::fromString('anna@example.com'));
        self::assertNotSame('a-long-password', $user->password()->value());
        /** @var PasswordHasherFactoryInterface $factory */
        $factory = self::getContainer()->get('security.password_hasher_factory');
        self::assertTrue($factory->getPasswordHasher(\App\Identity\Infrastructure\Security\SecurityUser::class)->verify($user->password()->value(), 'a-long-password'));
    }

    public function testGeneratesAUuidV7(): void
    {
        $result = $this->handler->__invoke(new RegisterUser('anna@example.com', 'a-long-password'));

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-/', $result->id);
    }

    public function testRejectsADuplicateEmailCaseInsensitively(): void
    {
        $this->handler->__invoke(new RegisterUser('anna@example.com', 'a-long-password'));

        $this->expectException(EmailAlreadyRegistered::class);

        $this->handler->__invoke(new RegisterUser('ANNA@example.com', 'another-password'));
    }

    public function testRejectsAnInvalidEmail(): void
    {
        $this->expectException(InvalidEmail::class);

        $this->handler->__invoke(new RegisterUser('nope', 'a-long-password'));
    }
}
