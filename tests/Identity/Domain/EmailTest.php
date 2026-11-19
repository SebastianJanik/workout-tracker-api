<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\Exception\InvalidEmail;
use App\Identity\Domain\ValueObject\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testIsTrimmedAndLowerCased(): void
    {
        self::assertSame('anna@example.com', Email::fromString('  Anna@Example.COM ')->value());
    }

    public function testEqualsIgnoresCase(): void
    {
        self::assertTrue(Email::fromString('A@b.io')->equals(Email::fromString('a@B.io')));
    }

    #[DataProvider('invalidEmails')]
    public function testRejectsInvalidEmails(string $raw): void
    {
        $this->expectException(InvalidEmail::class);

        Email::fromString($raw);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'no at sign' => ['anna.example.com'];
        yield 'no domain' => ['anna@'];
        yield 'spaces inside' => ['an na@example.com'];
    }
}
