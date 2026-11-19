<?php

declare(strict_types=1);

namespace App\Tests\SharedKernel\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    public function testAcceptsAUuidAndNormalisesToLowerCase(): void
    {
        $id = UserId::fromString('0194F2C8-7A3B-7C1D-8E2F-123456789ABC');

        self::assertSame('0194f2c8-7a3b-7c1d-8e2f-123456789abc', $id->value());
    }

    public function testRejectsANonUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserId::fromString('not-a-uuid');
    }

    public function testIsStringable(): void
    {
        self::assertSame('0194f2c8-7a3b-7c1d-8e2f-123456789abc', (string) UserId::fromString('0194f2c8-7a3b-7c1d-8e2f-123456789abc'));
    }

    public function testEqualsComparesValues(): void
    {
        $a = UserId::fromString('0194f2c8-7a3b-7c1d-8e2f-123456789abc');

        self::assertTrue($a->equals(UserId::fromString('0194F2C8-7A3B-7C1D-8E2F-123456789ABC')));
        self::assertFalse($a->equals(UserId::fromString('0194f2c8-7a3b-7c1d-8e2f-123456789abd')));
    }
}
