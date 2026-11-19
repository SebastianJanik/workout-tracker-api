<?php

declare(strict_types=1);

namespace App\Tests\Identity\Application;

use App\Identity\Application\Command\RegisterUser;
use App\Identity\Application\Command\RegisterUserHandler;
use App\Identity\Application\Query\GetUserProfile;
use App\Identity\Application\Query\GetUserProfileHandler;
use App\Identity\Domain\Exception\UserNotFound;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Real handlers and test database (rolled back by DAMA).
 */
final class GetUserProfileHandlerTest extends KernelTestCase
{
    private GetUserProfileHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var GetUserProfileHandler $handler */
        $handler = self::getContainer()->get(GetUserProfileHandler::class);
        $this->handler = $handler;
    }

    public function testReturnsIdAndEmailOfTheUser(): void
    {
        /** @var RegisterUserHandler $register */
        $register = self::getContainer()->get(RegisterUserHandler::class);
        $registered = $register->__invoke(new RegisterUser('anna@example.com', 'a-long-password'));

        $profile = $this->handler->__invoke(new GetUserProfile('anna@example.com'));

        self::assertSame($registered->id, $profile->id);
        self::assertSame('anna@example.com', $profile->email);
    }

    public function testUnknownUserThrows(): void
    {
        $this->expectException(UserNotFound::class);

        $this->handler->__invoke(new GetUserProfile('nobody@example.com'));
    }
}
