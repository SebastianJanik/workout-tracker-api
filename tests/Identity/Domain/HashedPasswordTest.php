<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\ValueObject\HashedPassword;
use PHPUnit\Framework\TestCase;

final class HashedPasswordTest extends TestCase
{
    public function testKeepsTheHash(): void
    {
        self::assertSame('$2y$hash', HashedPassword::fromHash('$2y$hash')->value());
    }

    public function testRejectsAnEmptyHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HashedPassword::fromHash('');
    }
}
