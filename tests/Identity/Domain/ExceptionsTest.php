<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\Exception\InvalidEmail;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\ValueObject\Email;
use App\SharedKernel\Domain\Exception\TranslatableException;
use PHPUnit\Framework\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testEmailAlreadyRegisteredCarriesAKeyAndTheEmail(): void
    {
        $exception = EmailAlreadyRegistered::withEmail(Email::fromString('A@b.io'));

        self::assertInstanceOf(TranslatableException::class, $exception);
        self::assertSame('identity', $exception->translationDomain());
        self::assertSame('email.already_registered', $exception->messageKey());
        self::assertSame(['%email%' => 'a@b.io'], $exception->messageParameters());
    }

    public function testInvalidEmailCarriesAKeyAndTheRawValue(): void
    {
        $exception = InvalidEmail::because('nope');

        self::assertSame('email.invalid', $exception->messageKey());
        self::assertSame(['%email%' => 'nope'], $exception->messageParameters());
    }

    public function testUserNotFoundCarriesAKeyAndTheEmail(): void
    {
        $exception = UserNotFound::withEmail(Email::fromString('a@b.io'));

        self::assertSame('user.not_found', $exception->messageKey());
        self::assertSame(['%email%' => 'a@b.io'], $exception->messageParameters());
    }
}
