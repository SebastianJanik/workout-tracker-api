<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Doctrine;

use App\Shared\Infrastructure\Doctrine\TimestampVersionComparator;
use Doctrine\Migrations\Version\Version;
use PHPUnit\Framework\TestCase;

final class TimestampVersionComparatorTest extends TestCase
{
    public function testOrdersByTimestampAcrossContexts(): void
    {
        $versions = [
            new Version('DoctrineMigrations\Strength\Version20261105090000'),
            new Version('DoctrineMigrations\Identity\Version20261106090000'),
            new Version('DoctrineMigrations\Coaching\Version20261107090000'),
            new Version('DoctrineMigrations\Shared\Version20261104090000'),
        ];

        usort($versions, new TimestampVersionComparator()->compare(...));

        self::assertSame(
            [
                'DoctrineMigrations\Shared\Version20261104090000',
                'DoctrineMigrations\Strength\Version20261105090000',
                'DoctrineMigrations\Identity\Version20261106090000',
                'DoctrineMigrations\Coaching\Version20261107090000',
            ],
            array_map('strval', $versions),
        );
    }

    public function testSameTimestampFallsBackToFullName(): void
    {
        $comparator = new TimestampVersionComparator();

        $a = new Version('DoctrineMigrations\Coaching\Version20261104090000');
        $b = new Version('DoctrineMigrations\Identity\Version20261104090000');

        self::assertLessThan(0, $comparator->compare($a, $b));
        self::assertGreaterThan(0, $comparator->compare($b, $a));
        self::assertSame(0, $comparator->compare($a, $a));
    }
}
