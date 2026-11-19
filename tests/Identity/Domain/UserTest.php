<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\Aggregate\User;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\SharedKernel\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testRegisterBuildsAUserWithTheDefaultRole(): void
    {
        $id = UserId::fromString('0194f2c8-7a3b-7c1d-8e2f-123456789abc');
        $at = new \DateTimeImmutable('2026-11-19 10:00:00');

        $user = User::register($id, Email::fromString('anna@example.com'), HashedPassword::fromHash('hash'), $at);

        self::assertTrue($user->id()->equals($id));
        self::assertSame('anna@example.com', $user->email()->value());
        self::assertSame('hash', $user->password()->value());
        self::assertSame(['ROLE_USER'], $user->roles());
        self::assertSame($at, $user->registeredAt());
    }
}
